<?php

namespace Tests\Feature\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\SiteType;
use App\Enums\UserType;
use App\Models\Produit;
use App\Models\ProduitSite;
use App\Models\Stock;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteContext;
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

    private Site $usineA;
    private Site $usineB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usineA = Site::firstOrCreate(
            ['code' => 'GLB-A'],
            ['nom' => 'Site Alpha', 'type' => SiteType::USINE->value, 'statut' => 'active']
        );
        $this->usineB = Site::firstOrCreate(
            ['code' => 'GLB-B'],
            ['nom' => 'Site Beta', 'type' => SiteType::USINE->value, 'statut' => 'active']
        );

        // Contexte site A par défaut
        app(SiteContext::class)->setCurrentSiteId($this->usineA->id);
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
            'code'      => 'GLBT-002',
            'type'      => ProduitType::MATERIEL->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'is_global' => true,
            'site_id'   => null,
        ]);

        $this->assertTrue((bool) $produit->is_global);
        $this->assertNull($produit->site_id);
    }

    public function test_produit_local_a_is_global_false_par_defaut(): void
    {
        $produit = Produit::create([
            'nom'       => 'Produit local test',
            'code'      => 'GLBT-003',
            'type'      => ProduitType::MATERIEL->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_achat' => 500,
            'is_global'  => false,
        ]);

        $this->assertFalse((bool) $produit->is_global);
        // HasSiteScope remplit usine_id automatiquement depuis le contexte
        $this->assertEquals($this->usineA->id, $produit->site_id);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 2. Visibilité cross-usine
    // ──────────────────────────────────────────────────────────────────────

    public function test_produit_global_visible_depuis_usine_a(): void
    {
        $global = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Global visible partout',
            'code'      => 'GLBT-004',
            'type'      => ProduitType::SERVICE->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_vente' => 2000,
            'is_global' => true,
            'site_id'   => null,
        ]);

        app(SiteContext::class)->setCurrentSiteId($this->usineA->id);

        $ids = Produit::withoutGlobalScope('site')->pluck('id')->toArray();
        // Le scope usine laisse passer les is_global=true
        $this->assertContains($global->id, Produit::pluck('id')->toArray());
    }

    public function test_produit_global_visible_depuis_usine_b(): void
    {
        $global = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Global toutes usines',
            'code'      => 'GLBT-005',
            'type'      => ProduitType::SERVICE->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_vente' => 3000,
            'is_global' => true,
            'site_id'   => null,
        ]);

        app(SiteContext::class)->setCurrentSiteId($this->usineB->id);

        $this->assertContains($global->id, Produit::pluck('id')->toArray());
    }

    public function test_produit_local_usine_a_invisible_depuis_usine_b(): void
    {
        // Produit créé dans le contexte usine A
        app(SiteContext::class)->setCurrentSiteId($this->usineA->id);

        $local = Produit::create([
            'nom'       => 'Local usine A seulement',
            'code'      => 'GLBT-006',
            'type'      => ProduitType::MATERIEL->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_achat' => 800,
            'is_global'  => false,
        ]);

        $this->assertEquals($this->usineA->id, $local->site_id);

        // Basculer sur usine B
        app(SiteContext::class)->setCurrentSiteId($this->usineB->id);

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
            'code'      => 'GLBT-007',
            'type'      => ProduitType::MATERIEL->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_achat' => 600,
            'is_global' => true,
            'site_id'   => null,
        ]);

        // Nouvelle usine créée APRÈS le produit global
        $nouvelleUsine = Site::create([
            'nom'    => 'Nouveau Site Gamma',
            'code'   => 'GLB-NEW',
            'type'   => SiteType::USINE->value,
            'statut' => 'active',
        ]);

        // Le hook Site::created doit avoir créé la config locale
        $config = ProduitSite::where('produit_id', $global->id)
            ->where('site_id', $nouvelleUsine->id)
            ->first();

        $this->assertNotNull($config, 'ProduitUsine doit être créé automatiquement pour la nouvelle usine');
        $this->assertFalse((bool) $config->is_active, 'is_active doit être false par défaut');
    }

    public function test_nouvelle_usine_recoit_stock_pour_produits_globaux_stockables(): void
    {
        $global = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Produit global stockable',
            'code'      => 'GLBT-008',
            'type'      => ProduitType::MATERIEL->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_achat' => 400,
            'is_global' => true,
            'site_id'   => null,
        ]);

        $nouvelleUsine = Site::create([
            'nom'    => 'Site Delta Stock',
            'code'   => 'GLB-DELTA',
            'type'   => SiteType::USINE->value,
            'statut' => 'active',
        ]);

        $stock = Stock::where('produit_id', $global->id)
            ->where('site_id', $nouvelleUsine->id)
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
            'code'      => 'GLBT-009',
            'type'      => ProduitType::SERVICE->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_vente' => 1000,
            'is_global' => true,
            'site_id'   => null,
        ]);

        $produitAvecConfig = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Avec config usine A',
            'code'      => 'GLBT-010',
            'type'      => ProduitType::SERVICE->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_vente' => 1200,
            'is_global' => true,
            'site_id'   => null,
        ]);

        ProduitSite::create([
            'produit_id' => $produitAvecConfig->id,
            'site_id'   => $this->usineA->id,
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
            'code'      => 'GLBT-011',
            'type'      => ProduitType::SERVICE->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_vente' => 900,
            'is_global' => true,
            'site_id'   => null,
        ]);

        $inactif = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Inactif local',
            'code'      => 'GLBT-012',
            'type'      => ProduitType::SERVICE->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_vente' => 900,
            'is_global' => true,
            'site_id'   => null,
        ]);

        ProduitSite::create(['produit_id' => $actif->id,   'site_id' => $this->usineA->id, 'is_active' => true]);
        ProduitSite::create(['produit_id' => $inactif->id, 'site_id' => $this->usineA->id, 'is_active' => false]);

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
            ->withHeader('X-Site-Id', (string) $this->usineA->id)
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
            'site_id'   => null,
        ]);
    }

    public function test_api_liste_inclut_produits_globaux_dans_contexte_usine(): void
    {
        $global = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Global liste',
            'code'      => 'GLBT-013',
            'type'      => ProduitType::MATERIEL->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_achat' => 300,
            'is_global' => true,
            'site_id'   => null,
        ]);

        // Créer un stock pour que l'assertation de la relation fonctionne
        Stock::create(['produit_id' => $global->id, 'site_id' => $this->usineA->id, 'qte_stock' => 0]);

        $user = $this->makeStaffWithPermission('produits.read');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Site-Id', (string) $this->usineA->id)
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
        $role = Role::findOrCreate('admin_entreprise', 'web');

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

        $user->assignRole('admin_entreprise');
        $user->sites()->attach($this->usineA->id, ['role' => 'manager', 'is_default' => true]);
        return $user;
    }
}
