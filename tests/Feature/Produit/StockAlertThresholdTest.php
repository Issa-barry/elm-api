<?php

namespace Tests\Feature\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\UserType;
use App\Models\Parametre;
use App\Models\Produit;
use App\Models\User;
use App\Notifications\ProduitRuptureStockNotification;
use App\Observers\ProduitObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockAlertThresholdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles nécessaires pour le ciblage des destinataires
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('manager', 'web');

        // Paramètres de stock par défaut (seuil global = 10, notifications actives, cooldown = 0)
        Parametre::updateOrCreate(['cle' => Parametre::CLE_SEUIL_STOCK_FAIBLE], [
            'valeur' => '10', 'type' => Parametre::TYPE_INTEGER, 'groupe' => Parametre::GROUPE_GENERAL,
        ]);
        Parametre::updateOrCreate(['cle' => Parametre::CLE_NOTIFICATIONS_STOCK_ACTIVES], [
            'valeur' => '1', 'type' => Parametre::TYPE_BOOLEAN, 'groupe' => Parametre::GROUPE_GENERAL,
        ]);
        // Cooldown à 0 pour les tests (pas de délai)
        Parametre::updateOrCreate(['cle' => Parametre::CLE_NOTIFICATIONS_STOCK_COOLDOWN_MINUTES], [
            'valeur' => '0', 'type' => Parametre::TYPE_INTEGER, 'groupe' => Parametre::GROUPE_GENERAL,
        ]);

        // Vider le cache Parametre pour forcer la relecture
        Parametre::clearCache();
    }

    private function makeAdminStaff(): User
    {
        $user = User::create([
            'type'            => UserType::STAFF->value,
            'nom'             => 'Admin',
            'prenom'          => 'Test',
            'phone'           => '+224620000001',
            'password'        => bcrypt('secret1234'),
            'pays'            => 'Guinée',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'ville'           => 'Conakry',
            'quartier'        => 'Kaloum',
        ]);
        $user->assignRole('admin');
        return $user;
    }

    // ---------------------------------------------------------------
    // 1. Franchissement seuil personnalisé => notification low_stock
    // ---------------------------------------------------------------

    public function test_notification_low_stock_envoyee_quand_stock_franchit_seuil_personnalise(): void
    {
        Notification::fake();
        $this->makeAdminStaff();

        // Produit critique avec seuil personnalisé = 20, stock initial = 25
        $produit = Produit::factory()->critique()->withSeuil(20)->withStock(25)->create();

        // Le stock passe de 25 à 15 (en dessous du seuil 20)
        $produit->qte_stock = 15;
        $produit->save();

        Notification::assertSentTo(
            User::where('type', UserType::STAFF->value)->role(['admin', 'manager'])->get(),
            ProduitRuptureStockNotification::class,
            function (ProduitRuptureStockNotification $notification, $channels) {
                $payload = $notification->toArray(null);
                return $payload['type'] === 'low_stock'
                    && $payload['qte_stock'] === 15
                    && $payload['seuil_effectif'] === 20;
            }
        );
    }

    // ---------------------------------------------------------------
    // 2. Stock déjà sous seuil => pas de nouvelle notification
    // ---------------------------------------------------------------

    public function test_pas_de_notification_si_stock_deja_sous_seuil(): void
    {
        Notification::fake();
        $this->makeAdminStaff();

        // Produit critique avec seuil = 20, stock initial DÉJÀ sous le seuil = 10
        $produit = Produit::factory()->critique()->withSeuil(20)->withStock(10)->create();

        // Le stock baisse encore (reste sous le seuil), pas de franchissement
        $produit->qte_stock = 8;
        $produit->save();

        Notification::assertNothingSent();
    }

    // ---------------------------------------------------------------
    // 3. Seuil personnalisé null => fallback global (10)
    // ---------------------------------------------------------------

    public function test_fallback_seuil_global_quand_seuil_personnalise_null(): void
    {
        Notification::fake();
        $this->makeAdminStaff();

        // Pas de seuil personnalisé, seuil global = 10, stock initial = 15
        $produit = Produit::factory()->critique()->withStock(15)->create();
        $this->assertNull($produit->seuil_alerte_stock);
        $this->assertEquals(10, $produit->low_stock_threshold);

        // Stock passe de 15 à 8 (franchit le seuil global 10)
        $produit->qte_stock = 8;
        $produit->save();

        Notification::assertSentTo(
            User::where('type', UserType::STAFF->value)->role(['admin', 'manager'])->get(),
            ProduitRuptureStockNotification::class,
            function (ProduitRuptureStockNotification $notification, $channels) {
                $payload = $notification->toArray(null);
                return $payload['type'] === 'low_stock'
                    && $payload['seuil_effectif'] === 10;
            }
        );
    }

    // ---------------------------------------------------------------
    // 4. Seuil effectif = 0 => alerte uniquement à rupture
    // ---------------------------------------------------------------

    public function test_seuil_zero_alerte_uniquement_a_rupture(): void
    {
        Notification::fake();
        $this->makeAdminStaff();

        // Seuil personnalisé = 0, stock = 5 (au-dessus de 0)
        $produit = Produit::factory()->critique()->withSeuil(0)->withStock(5)->create();

        // Stock passe à 3 — ne doit PAS déclencher (seuil=0, donc seulement à 0)
        $produit->qte_stock = 3;
        $produit->save();

        Notification::assertNothingSent();
    }

    public function test_seuil_zero_alerte_envoyee_a_rupture(): void
    {
        Notification::fake();
        $this->makeAdminStaff();

        // Seuil personnalisé = 0, stock initial = 5
        $produit = Produit::factory()->critique()->withSeuil(0)->withStock(5)->create();

        // Stock tombe à 0 => rupture_stock
        $produit->qte_stock = 0;
        $produit->save();

        Notification::assertSentTo(
            User::where('type', UserType::STAFF->value)->role(['admin', 'manager'])->get(),
            ProduitRuptureStockNotification::class,
            function (ProduitRuptureStockNotification $notification, $channels) {
                $payload = $notification->toArray(null);
                return $payload['type'] === 'rupture_stock';
            }
        );
    }

    // ---------------------------------------------------------------
    // 5. Stock passe direct au-dessus du seuil à 0 => rupture_stock
    // ---------------------------------------------------------------

    public function test_notification_rupture_stock_quand_stock_passe_a_zero(): void
    {
        Notification::fake();
        $this->makeAdminStaff();

        // Seuil = 5, stock = 20 (bien au-dessus)
        $produit = Produit::factory()->critique()->withSeuil(5)->withStock(20)->create();

        // Stock tombe à 0 (franchit le seuil ET rupture)
        $produit->qte_stock = 0;
        $produit->save();

        Notification::assertSentTo(
            User::where('type', UserType::STAFF->value)->role(['admin', 'manager'])->get(),
            ProduitRuptureStockNotification::class,
            function (ProduitRuptureStockNotification $notification, $channels) {
                $payload = $notification->toArray(null);
                return $payload['type'] === 'rupture_stock';
            }
        );
    }

    // ---------------------------------------------------------------
    // 6. Produit non critique => jamais de notification
    // ---------------------------------------------------------------

    public function test_pas_de_notification_pour_produit_non_critique(): void
    {
        Notification::fake();
        $this->makeAdminStaff();

        // Produit NON critique
        $produit = Produit::factory()->withSeuil(10)->withStock(20)->create([
            'is_critique' => false,
        ]);

        $produit->qte_stock = 0;
        $produit->save();

        Notification::assertNothingSent();
    }

    // ---------------------------------------------------------------
    // 7. Accesseur low_stock_threshold renvoie la bonne valeur
    // ---------------------------------------------------------------

    public function test_low_stock_threshold_retourne_seuil_personnalise(): void
    {
        $produit = Produit::factory()->withSeuil(42)->create();
        $this->assertEquals(42, $produit->low_stock_threshold);
    }

    public function test_low_stock_threshold_retourne_global_si_null(): void
    {
        Parametre::clearCache();
        $produit = Produit::factory()->create(['seuil_alerte_stock' => null]);
        $this->assertEquals(10, $produit->low_stock_threshold);
    }

    // ---------------------------------------------------------------
    // 8. is_low_stock respecte le seuil effectif
    // ---------------------------------------------------------------

    public function test_is_low_stock_vrai_avec_seuil_personnalise(): void
    {
        $produit = Produit::factory()->withSeuil(20)->withStock(15)->create();
        $this->assertTrue($produit->is_low_stock);
    }

    public function test_is_low_stock_faux_au_dessus_seuil(): void
    {
        $produit = Produit::factory()->withSeuil(10)->withStock(50)->create();
        $this->assertFalse($produit->is_low_stock);
    }

    public function test_is_low_stock_faux_pour_service(): void
    {
        $produit = Produit::factory()->create([
            'type'        => ProduitType::SERVICE->value,
            'statut'      => ProduitStatut::ACTIF->value,
            'qte_stock'   => 0,
            'prix_achat'  => 500,
        ]);
        $this->assertFalse($produit->is_low_stock);
    }
}
