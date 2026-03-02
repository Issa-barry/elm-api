<?php

namespace Tests\Feature\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\UsineRole;
use App\Enums\UsineType;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Usine;
use App\Models\User;
use App\Services\UsineContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tests de la vue consolidée multi-usines via X-Usine-Id: all.
 *
 * Couvre :
 *  - Usine précise → qte_stock = stock de cette usine uniquement
 *  - X-Usine-Id: all → qte_stock = SUM sur toutes les usines
 *  - Filtre in_stock en mode all-usines
 *  - Utilisateur non-siège → 403 sur X-Usine-Id: all
 *  - Siège avec default_usine_id → all-usines avec X-Usine-Id: all
 */
class ProduitStockAllUsinesTest extends TestCase
{
    use RefreshDatabase;

    private Usine $siege;
    private Usine $usineA;
    private Usine $usineB;
    private User  $siegeUser;
    private User  $staffUser;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        Permission::findOrCreate('produits.read', 'web');

        // Usine siège
        $this->siege = Usine::create([
            'nom'    => 'Siege Central',
            'code'   => 'ALLSTK-SIEGE',
            'type'   => UsineType::SIEGE->value,
            'statut' => 'active',
        ]);

        // Deux usines de production
        $this->usineA = Usine::create([
            'nom'    => 'Usine All-Stock A',
            'code'   => 'ALLSTK-A',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        $this->usineB = Usine::create([
            'nom'    => 'Usine All-Stock B',
            'code'   => 'ALLSTK-B',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        // Utilisateur siège (affecté au siège avec rôle owner_siege)
        $this->siegeUser = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usineA->id,   // default pointe sur A, pas siège
        ]);
        $this->siegeUser->usines()->attach($this->siege->id, [
            'role'       => UsineRole::OWNER_SIEGE->value,
            'is_default' => false,
        ]);
        $this->siegeUser->givePermissionTo('produits.read');

        // Utilisateur staff ordinaire (uniquement affecté à l'usine A)
        $this->staffUser = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usineA->id,
        ]);
        $this->staffUser->usines()->attach($this->usineA->id, [
            'role'       => UsineRole::STAFF->value,
            'is_default' => true,
        ]);
        $this->staffUser->givePermissionTo('produits.read');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function creerProduit(string $suffix, Usine $usine): Produit
    {
        app(UsineContext::class)->setCurrentUsineId($usine->id);

        return Produit::withoutGlobalScopes()->create([
            'usine_id'    => $usine->id,
            'nom'         => "Produit {$suffix}",
            'code'        => "ALLSTK-{$suffix}",
            'type'        => ProduitType::MATERIEL->value,
            'statut'      => ProduitStatut::ACTIF->value,
            'prix_achat'  => 100,
            'is_global'   => false,
            'is_critique' => false,
        ]);
    }

    private function creerProduitGlobal(string $suffix): Produit
    {
        app(UsineContext::class)->setCurrentUsineId($this->usineA->id);

        return Produit::withoutGlobalScopes()->create([
            'usine_id'    => null,
            'nom'         => "Produit Global {$suffix}",
            'code'        => "ALLSTK-G-{$suffix}",
            'type'        => ProduitType::MATERIEL->value,
            'statut'      => ProduitStatut::ACTIF->value,
            'prix_achat'  => 100,
            'is_global'   => true,
            'is_critique' => false,
        ]);
    }

    private function setStock(Produit $produit, Usine $usine, int $qte): Stock
    {
        return Stock::updateOrCreate(
            ['produit_id' => $produit->id, 'usine_id' => $usine->id],
            ['qte_stock' => $qte, 'seuil_alerte_stock' => 5]
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Vue par usine précise
    // ──────────────────────────────────────────────────────────────────────

    public function test_vue_usine_A_retourne_uniquement_son_stock(): void
    {
        $produit = $this->creerProduitGlobal('P1-A');
        $this->setStock($produit, $this->usineA, 1000);
        $this->setStock($produit, $this->usineB, 993);

        Sanctum::actingAs($this->siegeUser);

        $response = $this->getJson(
            "/api/v1/produits/{$produit->id}",
            ['X-Usine-Id' => (string) $this->usineA->id]
        );

        $response->assertOk();
        $this->assertEquals(1000, $response->json('data.qte_stock'));
    }

    public function test_vue_usine_B_retourne_uniquement_son_stock(): void
    {
        $produit = $this->creerProduitGlobal('P1-B');
        $this->setStock($produit, $this->usineA, 1000);
        $this->setStock($produit, $this->usineB, 993);

        Sanctum::actingAs($this->siegeUser);

        $response = $this->getJson(
            "/api/v1/produits/{$produit->id}",
            ['X-Usine-Id' => (string) $this->usineB->id]
        );

        $response->assertOk();
        $this->assertEquals(993, $response->json('data.qte_stock'));
    }

    // ──────────────────────────────────────────────────────────────────────
    // Vue consolidée X-Usine-Id: all
    // ──────────────────────────────────────────────────────────────────────

    public function test_vue_all_retourne_somme_des_stocks(): void
    {
        $produit = $this->creerProduitGlobal('P2');
        $this->setStock($produit, $this->usineA, 1000);
        $this->setStock($produit, $this->usineB, 993);

        Sanctum::actingAs($this->siegeUser);

        $response = $this->getJson(
            "/api/v1/produits/{$produit->id}",
            ['X-Usine-Id' => 'all']
        );

        $response->assertOk();
        $this->assertEquals(1993, $response->json('data.qte_stock'));
    }

    public function test_vue_all_index_retourne_somme_par_produit(): void
    {
        $p1 = $this->creerProduitGlobal('IDX1');
        $p2 = $this->creerProduitGlobal('IDX2');

        $this->setStock($p1, $this->usineA, 1000);
        $this->setStock($p1, $this->usineB, 993);
        $this->setStock($p2, $this->usineA, 1000);
        $this->setStock($p2, $this->usineB, 1000);

        Sanctum::actingAs($this->siegeUser);

        $response = $this->getJson('/api/v1/produits', ['X-Usine-Id' => 'all']);

        $response->assertOk();

        $data = collect($response->json('data'));

        $qteP1 = $data->firstWhere('id', $p1->id)['qte_stock'];
        $qteP2 = $data->firstWhere('id', $p2->id)['qte_stock'];

        $this->assertEquals(1993, $qteP1);
        $this->assertEquals(2000, $qteP2);
    }

    public function test_vue_all_in_stock_vrai_si_au_moins_une_usine_a_du_stock(): void
    {
        // Produit en stock dans A seulement
        $produit = $this->creerProduitGlobal('INST1');
        $this->setStock($produit, $this->usineA, 50);
        $this->setStock($produit, $this->usineB, 0);

        Sanctum::actingAs($this->siegeUser);

        $response = $this->getJson('/api/v1/produits?in_stock=1', ['X-Usine-Id' => 'all']);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($produit->id, $ids);
    }

    public function test_vue_all_in_stock_faux_si_toutes_usines_a_zero(): void
    {
        $produit = $this->creerProduitGlobal('INST2');
        $this->setStock($produit, $this->usineA, 0);
        $this->setStock($produit, $this->usineB, 0);

        Sanctum::actingAs($this->siegeUser);

        $response = $this->getJson('/api/v1/produits?in_stock=0', ['X-Usine-Id' => 'all']);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($produit->id, $ids);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Sécurité : utilisateur non-siège bloqué
    // ──────────────────────────────────────────────────────────────────────

    public function test_non_siege_ne_peut_pas_utiliser_x_usine_id_all(): void
    {
        Sanctum::actingAs($this->staffUser);

        $this->getJson('/api/v1/produits', ['X-Usine-Id' => 'all'])
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Siège avec default_usine_id → all-usines quand X-Usine-Id: all
    // ──────────────────────────────────────────────────────────────────────

    public function test_siege_avec_default_usine_id_obtient_vue_all_avec_header(): void
    {
        // siegeUser a default_usine_id = usineA ; sans header => vue consolidée (nouveau contrat)
        $produit = $this->creerProduitGlobal('DEF1');
        $this->setStock($produit, $this->usineA, 1000);
        $this->setStock($produit, $this->usineB, 993);

        Sanctum::actingAs($this->siegeUser);

        // Sans header → consolidé
        $responseUsineA = $this->getJson("/api/v1/produits/{$produit->id}");
        $responseUsineA->assertOk();
        $this->assertEquals(1993, $responseUsineA->json('data.qte_stock'));

        // Avec header all → consolidé
        $responseAll = $this->getJson(
            "/api/v1/produits/{$produit->id}",
            ['X-Usine-Id' => 'all']
        );
        $responseAll->assertOk();
        $this->assertEquals(1993, $responseAll->json('data.qte_stock'));
    }

    // ──────────────────────────────────────────────────────────────────────
    // Isolation : mode all ne mélange pas les produits d'usines
    // ──────────────────────────────────────────────────────────────────────

    public function test_vue_all_liste_les_produits_de_toutes_les_usines(): void
    {
        $pA = $this->creerProduit('ISOL-A', $this->usineA);
        $pB = $this->creerProduit('ISOL-B', $this->usineB);

        $this->setStock($pA, $this->usineA, 100);
        $this->setStock($pB, $this->usineB, 200);

        Sanctum::actingAs($this->siegeUser);

        $response = $this->getJson('/api/v1/produits', ['X-Usine-Id' => 'all']);

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($pA->id, $ids);
        $this->assertContains($pB->id, $ids);
    }
}
