<?php

namespace Tests\Feature\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\UsineType;
use App\Enums\UserType;
use App\Models\Produit;
use App\Models\ProduitUsine;
use App\Models\Stock;
use App\Models\Usine;
use App\Models\User;
use App\Services\UsineContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests du modèle produit global.
 *
 * Couvre :
 *  - La colonne is_global (et absence de is_systeme)
 *  - La visibilité cross-usine des produits globaux
 *  - La création automatique de ProduitUsine/Stock pour toutes les usines
 *  - L'invisibilité d'un produit local depuis une autre usine
 *  - L'API create/read/archive d'un produit global (HTTP)
 */
class ProduitGlobalTest extends TestCase
{
    use RefreshDatabase;

    private Usine $usineA;
    private Usine $usineB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usineA = Usine::firstOrCreate(
            ['code' => 'GLB-A'],
            ['nom' => 'Usine Alpha', 'type' => UsineType::USINE->value, 'statut' => 'active']
        );
        $this->usineB = Usine::firstOrCreate(
            ['code' => 'GLB-B'],
            ['nom' => 'Usine Beta', 'type' => UsineType::USINE->value, 'statut' => 'active']
        );

        // Contexte usine A par défaut
        app(UsineContext::class)->setCurrentUsineId($this->usineA->id);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 1. Colonne is_global (pas is_systeme)
    // ──────────────────────────────────────────────────────────────────────

    public function test_colonne_is_global_existe_et_is_systeme_absente(): void
    {
        $columns = Schema::getColumnListing('produits');

        $this->assertContains('is_global', $columns, 'La colonne is_global doit exister dans produits');
        $this->assertNotContains('is_systeme', $columns, 'La colonne is_systeme ne doit plus exister dans produits');
    }

    public function test_produit_global_cree_avec_is_global_true_et_usine_id_null(): void
    {
        $produit = Produit::create([
            'nom'       => 'Produit global test',
            'type'      => ProduitType::MATERIEL->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'is_global' => true,
            'usine_id'   => null,
        ]);

        $this->assertTrue((bool) $produit->is_global);
        $this->assertNull($produit->usine_id);
    }

    public function test_produit_local_a_is_global_false_par_defaut(): void
    {
        $produit = Produit::create([
            'nom'       => 'Produit local test',
            'type'      => ProduitType::MATERIEL->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_achat' => 500,
            'is_global'  => false,
        ]);

        $this->assertFalse((bool) $produit->is_global);
        // HasUsineScope remplit usine_id automatiquement depuis le contexte
        $this->assertEquals($this->usineA->id, $produit->usine_id);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 2. Visibilité cross-usine
    // ──────────────────────────────────────────────────────────────────────

    public function test_produit_global_visible_depuis_usine_a(): void
    {
        $global = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Global visible partout',
            'type'      => ProduitType::SERVICE->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_vente' => 2000,
            'is_global' => true,
            'usine_id'   => null,
        ]);

        app(UsineContext::class)->setCurrentUsineId($this->usineA->id);

        $ids = Produit::withoutGlobalScope('usine')->pluck('id')->toArray();
        // Le scope usine laisse passer les is_global=true
        $this->assertContains($global->id, Produit::pluck('id')->toArray());
    }

    public function test_produit_global_visible_depuis_usine_b(): void
    {
        $global = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Global toutes usines',
            'type'      => ProduitType::SERVICE->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_vente' => 3000,
            'is_global' => true,
            'usine_id'   => null,
        ]);

        app(UsineContext::class)->setCurrentUsineId($this->usineB->id);

        $this->assertContains($global->id, Produit::pluck('id')->toArray());
    }

    public function test_produit_local_usine_a_invisible_depuis_usine_b(): void
    {
        // Produit créé dans le contexte usine A
        app(UsineContext::class)->setCurrentUsineId($this->usineA->id);

        $local = Produit::create([
            'nom'       => 'Local usine A seulement',
            'type'      => ProduitType::MATERIEL->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_achat' => 800,
            'is_global'  => false,
        ]);

        $this->assertEquals($this->usineA->id, $local->usine_id);

        // Basculer sur usine B
        app(UsineContext::class)->setCurrentUsineId($this->usineB->id);

        $ids = Produit::pluck('id')->toArray();
        $this->assertNotContains($local->id, $ids, 'Un produit local de A ne doit pas être visible depuis B');
    }

    // ──────────────────────────────────────────────────────────────────────
    // 3. Création automatique ProduitUsine + Stock pour produit global
    // ──────────────────────────────────────────────────────────────────────

    public function test_nouvelle_usine_recoit_config_locale_pour_produits_globaux(): void
    {
        // Produit global existant avant la création de l'usine
        $global = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Produit global pre-usine',
            'type'      => ProduitType::MATERIEL->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_achat' => 600,
            'is_global' => true,
            'usine_id'   => null,
        ]);

        // Nouvelle usine créée APRÈS le produit global
        $nouvelleUsine = Usine::create([
            'nom'    => 'Nouvelle Usine Gamma',
            'code'   => 'GLB-NEW',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        // Le hook Usine::created doit avoir créé la config locale
        $config = ProduitUsine::where('produit_id', $global->id)
            ->where('usine_id', $nouvelleUsine->id)
            ->first();

        $this->assertNotNull($config, 'ProduitUsine doit être créé automatiquement pour la nouvelle usine');
        $this->assertFalse((bool) $config->is_active, 'is_active doit être false par défaut');
    }

    public function test_nouvelle_usine_recoit_stock_pour_produits_globaux_stockables(): void
    {
        $global = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Produit global stockable',
            'type'      => ProduitType::MATERIEL->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_achat' => 400,
            'is_global' => true,
            'usine_id'   => null,
        ]);

        $nouvelleUsine = Usine::create([
            'nom'    => 'Usine Delta Stock',
            'code'   => 'GLB-DELTA',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        $stock = Stock::where('produit_id', $global->id)
            ->where('usine_id', $nouvelleUsine->id)
            ->first();

        $this->assertNotNull($stock, 'Un Stock doit être créé pour la nouvelle usine');
        $this->assertEquals(0, $stock->qte_stock);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 4. Scopes de filtrage
    // ──────────────────────────────────────────────────────────────────────

    public function test_scope_visible_pour_usine_filtre_par_produit_usines(): void
    {
        $produitSansConfig = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Sans config usine',
            'type'      => ProduitType::SERVICE->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_vente' => 1000,
            'is_global' => true,
            'usine_id'   => null,
        ]);

        $produitAvecConfig = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Avec config usine A',
            'type'      => ProduitType::SERVICE->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_vente' => 1200,
            'is_global' => true,
            'usine_id'   => null,
        ]);

        ProduitUsine::create([
            'produit_id' => $produitAvecConfig->id,
            'usine_id'   => $this->usineA->id,
            'is_active'  => true,
        ]);

        $ids = Produit::withoutGlobalScopes()
            ->visiblePourUsine($this->usineA->id)
            ->pluck('id')
            ->toArray();

        $this->assertContains($produitAvecConfig->id, $ids);
        $this->assertNotContains($produitSansConfig->id, $ids);
    }

    public function test_scope_actif_dans_usine_ne_remonte_que_les_actifs_localement(): void
    {
        $actif = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Actif local',
            'type'      => ProduitType::SERVICE->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_vente' => 900,
            'is_global' => true,
            'usine_id'   => null,
        ]);

        $inactif = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Inactif local',
            'type'      => ProduitType::SERVICE->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_vente' => 900,
            'is_global' => true,
            'usine_id'   => null,
        ]);

        ProduitUsine::create(['produit_id' => $actif->id,   'usine_id' => $this->usineA->id, 'is_active' => true]);
        ProduitUsine::create(['produit_id' => $inactif->id, 'usine_id' => $this->usineA->id, 'is_active' => false]);

        $ids = Produit::withoutGlobalScopes()
            ->actifDansUsine($this->usineA->id)
            ->pluck('id')
            ->toArray();

        $this->assertContains($actif->id, $ids);
        $this->assertNotContains($inactif->id, $ids);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 5. Test HTTP — Création d'un produit global (admin)
    // ──────────────────────────────────────────────────────────────────────

    public function test_api_creation_produit_global_retourne_201(): void
    {
        $user = $this->makeStaffWithPermission('produits.create');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Usine-Id', (string) $this->usineA->id)
            ->postJson('/api/v1/produits', [
                'nom'       => 'Produit global API',
                'type'      => 'materiel',
                'prix_achat' => 2500,
                'prix_vente' => 3500,
                'qte_stock'  => 0,
                'is_global'  => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('produits', [
            'nom'       => 'Produit global api', // normalized
            'is_global' => 1,
            'usine_id'   => null,
        ]);
    }

    public function test_api_liste_inclut_produits_globaux_dans_contexte_usine(): void
    {
        $global = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Global liste',
            'type'      => ProduitType::MATERIEL->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_achat' => 300,
            'is_global' => true,
            'usine_id'   => null,
        ]);

        // Créer un stock pour que l'assertation de la relation fonctionne
        Stock::create(['produit_id' => $global->id, 'usine_id' => $this->usineA->id, 'qte_stock' => 0]);

        $user = $this->makeStaffWithPermission('produits.read');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Usine-Id', (string) $this->usineA->id)
            ->getJson('/api/v1/produits');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($global->id, $ids);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function makeStaffWithPermission(string ...$permissions): User
    {
        $role = Role::findOrCreate('admin', 'web');

        foreach ($permissions as $permission) {
            $perm = Permission::findOrCreate($permission, 'web');
            $role->givePermissionTo($perm);
        }

        $user = User::create([
            'type'            => UserType::STAFF->value,
            'nom'             => 'Admin',
            'prenom'          => 'Test',
            'phone'           => '+224620000099',
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
}
