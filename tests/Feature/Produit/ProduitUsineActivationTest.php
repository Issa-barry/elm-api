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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests de l'activation locale par usine et du catalogue POS.
 *
 * Couvre :
 *  - Activation indépendante entre usines
 *  - Refus d'activation si statut global != actif
 *  - Scope disponiblesPOS (actif global + actif local + en stock)
 *  - Endpoint GET /api/v1/produits/pos
 *  - Endpoints PATCH activer/desactiver
 */
class ProduitUsineActivationTest extends TestCase
{
    use RefreshDatabase;

    private Site $usineA;
    private Site $usineB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usineA = Site::firstOrCreate(
            ['code' => 'ACT-A'],
            ['nom' => 'Site Activation A', 'type' => SiteType::USINE->value, 'statut' => 'active']
        );
        $this->usineB = Site::firstOrCreate(
            ['code' => 'ACT-B'],
            ['nom' => 'Site Activation B', 'type' => SiteType::USINE->value, 'statut' => 'active']
        );

        app(SiteContext::class)->setCurrentSiteId($this->usineA->id);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 1. Indépendance des activations entre usines
    // ──────────────────────────────────────────────────────────────────────

    public function test_activation_usine_a_naffecte_pas_usine_b(): void
    {
        $produit = $this->creerProduitGlobal('Produit indépendant');

        // Affecter le produit aux deux usines
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineA->id, 'is_active' => false]);
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineB->id, 'is_active' => false]);

        // Activer uniquement dans usine A
        ProduitSite::where('produit_id', $produit->id)
            ->where('site_id', $this->usineA->id)
            ->update(['is_active' => true]);

        $configA = ProduitSite::where('produit_id', $produit->id)->where('site_id', $this->usineA->id)->first();
        $configB = ProduitSite::where('produit_id', $produit->id)->where('site_id', $this->usineB->id)->first();

        $this->assertTrue((bool) $configA->is_active, 'Usine A doit être active');
        $this->assertFalse((bool) $configB->is_active, 'Usine B doit rester inactive');
    }

    public function test_desactivation_usine_b_naffecte_pas_usine_a(): void
    {
        $produit = $this->creerProduitGlobal('Produit désactivation');

        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineA->id, 'is_active' => true]);
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineB->id, 'is_active' => true]);

        // Désactiver uniquement dans usine B
        ProduitSite::where('produit_id', $produit->id)
            ->where('site_id', $this->usineB->id)
            ->update(['is_active' => false]);

        $configA = ProduitSite::where('produit_id', $produit->id)->where('site_id', $this->usineA->id)->first();
        $configB = ProduitSite::where('produit_id', $produit->id)->where('site_id', $this->usineB->id)->first();

        $this->assertTrue((bool) $configA->is_active, 'Usine A doit rester active');
        $this->assertFalse((bool) $configB->is_active, 'Usine B doit être inactive');
    }

    // ──────────────────────────────────────────────────────────────────────
    // 2. Endpoint PATCH activer / désactiver
    // ──────────────────────────────────────────────────────────────────────

    public function test_api_activer_produit_dans_usine_retourne_200(): void
    {
        $produit = $this->creerProduitGlobal('API Activer');
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineA->id, 'is_active' => false]);

        $user = $this->makeStaffWithPermission('produits.update');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Site-Id', (string) $this->usineA->id)
            ->patchJson("/api/v1/produits/{$produit->id}/usines/{$this->usineA->id}/activer");

        $response->assertStatus(200);
        $this->assertDatabaseHas('produit_sites', [
            'produit_id' => $produit->id,
            'site_id'   => $this->usineA->id,
            'is_active'  => 1,
        ]);
    }

    public function test_api_activer_refuse_si_statut_global_pas_actif(): void
    {
        $produit = $this->creerProduitGlobal('Brouillon global', ProduitStatut::BROUILLON);
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineA->id, 'is_active' => false]);

        $user = $this->makeStaffWithPermission('produits.update');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Site-Id', (string) $this->usineA->id)
            ->patchJson("/api/v1/produits/{$produit->id}/usines/{$this->usineA->id}/activer");

        $response->assertStatus(400);
        $this->assertDatabaseHas('produit_sites', [
            'produit_id' => $produit->id,
            'site_id'   => $this->usineA->id,
            'is_active'  => 0,
        ]);
    }

    public function test_api_desactiver_produit_dans_usine_retourne_200(): void
    {
        $produit = $this->creerProduitGlobal('API Désactiver');
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineA->id, 'is_active' => true]);

        $user = $this->makeStaffWithPermission('produits.update');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Site-Id', (string) $this->usineA->id)
            ->patchJson("/api/v1/produits/{$produit->id}/usines/{$this->usineA->id}/desactiver");

        $response->assertStatus(200);
        $this->assertDatabaseHas('produit_sites', [
            'produit_id' => $produit->id,
            'site_id'   => $this->usineA->id,
            'is_active'  => 0,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 3. Scope disponiblesPOS
    // ──────────────────────────────────────────────────────────────────────

    public function test_produit_global_inactif_localement_absent_du_scope_pos(): void
    {
        $produit = $this->creerProduitGlobal('POS Inactif local');
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineA->id, 'is_active' => false]);
        Stock::firstOrCreate(['produit_id' => $produit->id, 'site_id' => $this->usineA->id], ['qte_stock' => 50]);

        $ids = Produit::withoutGlobalScopes()
            ->disponiblesPOS($this->usineA->id)
            ->pluck('id')
            ->toArray();

        $this->assertNotContains($produit->id, $ids, 'Un produit inactif localement ne doit pas apparaître au POS');
    }

    public function test_produit_global_actif_localement_en_stock_present_au_pos(): void
    {
        $produit = $this->creerProduitGlobal('POS Actif en stock');
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineA->id, 'is_active' => true]);
        Stock::firstOrCreate(['produit_id' => $produit->id, 'site_id' => $this->usineA->id], ['qte_stock' => 10]);

        $ids = Produit::withoutGlobalScopes()
            ->disponiblesPOS($this->usineA->id)
            ->pluck('id')
            ->toArray();

        $this->assertContains($produit->id, $ids, 'Un produit actif localement et en stock doit apparaître au POS');
    }

    public function test_produit_stockable_en_rupture_absent_du_pos(): void
    {
        $produit = $this->creerProduitGlobal('POS rupture');
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineA->id, 'is_active' => true]);
        Stock::firstOrCreate(['produit_id' => $produit->id, 'site_id' => $this->usineA->id], ['qte_stock' => 0]);

        $ids = Produit::withoutGlobalScopes()
            ->disponiblesPOS($this->usineA->id)
            ->pluck('id')
            ->toArray();

        $this->assertNotContains($produit->id, $ids, 'Un produit en rupture ne doit pas apparaître au POS');
    }

    public function test_service_actif_localement_present_au_pos_sans_stock(): void
    {
        $service = Produit::withoutGlobalScopes()->create([
            'nom'       => 'Service POS',
            'code'      => 'ACT-SVC-001',
            'type'      => ProduitType::SERVICE->value,
            'statut'    => ProduitStatut::ACTIF->value,
            'prix_vente' => 5000,
            'is_global' => true,
            'site_id'   => null,
        ]);
        ProduitSite::create(['produit_id' => $service->id, 'site_id' => $this->usineA->id, 'is_active' => true]);

        $ids = Produit::withoutGlobalScopes()
            ->disponiblesPOS($this->usineA->id)
            ->pluck('id')
            ->toArray();

        $this->assertContains($service->id, $ids, 'Un service actif localement doit être au POS sans stock');
    }

    public function test_produit_pos_usine_a_absent_du_pos_usine_b(): void
    {
        $produit = $this->creerProduitGlobal('POS isolation usine');

        // Actif en A, non affecté en B
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineA->id, 'is_active' => true]);
        Stock::firstOrCreate(['produit_id' => $produit->id, 'site_id' => $this->usineA->id], ['qte_stock' => 5]);

        $idsA = Produit::withoutGlobalScopes()->disponiblesPOS($this->usineA->id)->pluck('id')->toArray();
        $idsB = Produit::withoutGlobalScopes()->disponiblesPOS($this->usineB->id)->pluck('id')->toArray();

        $this->assertContains($produit->id, $idsA, 'Doit être au POS de A');
        $this->assertNotContains($produit->id, $idsB, 'Ne doit pas être au POS de B');
    }

    // ──────────────────────────────────────────────────────────────────────
    // 4. Endpoint GET /pos
    // ──────────────────────────────────────────────────────────────────────

    public function test_api_pos_retourne_uniquement_produits_actifs_localement(): void
    {
        $actif   = $this->creerProduitGlobal('POS API Actif');
        $inactif = $this->creerProduitGlobal('POS API Inactif');

        ProduitSite::create(['produit_id' => $actif->id,   'site_id' => $this->usineA->id, 'is_active' => true]);
        ProduitSite::create(['produit_id' => $inactif->id, 'site_id' => $this->usineA->id, 'is_active' => false]);
        Stock::firstOrCreate(['produit_id' => $actif->id, 'site_id' => $this->usineA->id], ['qte_stock' => 20]);

        $user = $this->makeStaffWithPermission('produits.read');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Site-Id', (string) $this->usineA->id)
            ->getJson('/api/v1/produits/pos');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains($actif->id, $ids);
        $this->assertNotContains($inactif->id, $ids);
    }

    public function test_api_pos_sans_header_usine_retourne_400(): void
    {
        $user = $this->makeStaffWithPermission('produits.read');

        // Sans X-Site-Id, SiteContext::getCurrentSiteId() retourne null
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/produits/pos');

        // Le middleware usine.context passe, mais le controller vérifie usineId non null
        $response->assertStatus(400);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 5. Affectation via endpoint POST /usines
    // ──────────────────────────────────────────────────────────────────────

    public function test_api_affecter_produit_a_usine_cree_config_locale(): void
    {
        $produit = $this->creerProduitGlobal('Affecter API');
        $user    = $this->makeStaffWithPermission('produits.update');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Site-Id', (string) $this->usineA->id)
            ->postJson("/api/v1/produits/{$produit->id}/usines", [
                'site_id'  => $this->usineB->id,
                'is_active' => false,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('produit_sites', [
            'produit_id' => $produit->id,
            'site_id'   => $this->usineB->id,
            'is_active'  => 0,
        ]);
    }

    public function test_api_affecter_deux_fois_retourne_409(): void
    {
        $produit = $this->creerProduitGlobal('Double affectation');
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineB->id, 'is_active' => false]);

        $user = $this->makeStaffWithPermission('produits.update');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Site-Id', (string) $this->usineA->id)
            ->postJson("/api/v1/produits/{$produit->id}/usines", [
                'site_id' => $this->usineB->id,
            ]);

        $response->assertStatus(409);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function creerProduitGlobal(string $nom, ProduitStatut $statut = ProduitStatut::ACTIF): Produit
    {
        return Produit::withoutGlobalScopes()->create([
            'nom'       => $nom,
            'code'      => substr(md5(uniqid($nom)), 0, 12),
            'type'      => ProduitType::MATERIEL->value,
            'statut'    => $statut->value,
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'is_global' => true,
            'site_id'   => null,
        ]);
    }

    private function makeStaffWithPermission(string ...$permissions): User
    {
        $role = Role::findOrCreate('admin_entreprise', 'web');

        foreach ($permissions as $permission) {
            $perm = Permission::findOrCreate($permission, 'web');
            $role->givePermissionTo($perm);
        }

        static $counter = 0;
        $counter++;

        $user = User::create([
            'type'            => UserType::STAFF->value,
            'nom'             => 'Staff',
            'prenom'          => 'Test',
            'phone'           => "+22462000{$counter}",
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
