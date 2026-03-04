<?php

namespace Tests\Feature\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\SiteType;
use App\Enums\UserType;
use App\Models\Produit;
use App\Models\ProduitSite;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests des prix locaux par usine (produit_usines).
 *
 * Couvre :
 *  - Fallback global → local si défini
 *  - Priorité du prix local sur le prix global
 *  - Prix différents entre usines A et B
 *  - Méthode prixEffectifDansUsine()
 *  - Accesseurs prixUsineEffectif / prixVenteEffectif / etc. sur ProduitUsine
 *  - Endpoint PATCH /usines/{usine_id}/prix
 */
class ProduitUsinePrixTest extends TestCase
{
    use RefreshDatabase;

    private Site $usineA;
    private Site $usineB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usineA = Site::firstOrCreate(
            ['code' => 'PRIX-A'],
            ['nom' => 'Site Prix A', 'type' => SiteType::USINE->value, 'statut' => 'active']
        );
        $this->usineB = Site::firstOrCreate(
            ['code' => 'PRIX-B'],
            ['nom' => 'Site Prix B', 'type' => SiteType::USINE->value, 'statut' => 'active']
        );

        app(SiteContext::class)->setCurrentSiteId($this->usineA->id);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 1. Méthode prixEffectifDansUsine() sur le modèle Produit
    // ──────────────────────────────────────────────────────────────────────

    public function test_fallback_global_si_aucun_prix_local_defini(): void
    {
        $produit = $this->creerProduitGlobal(prix_achat: 1000, prix_vente: 1800, prix_usine: 900);

        ProduitSite::create([
            'produit_id' => $produit->id,
            'site_id'   => $this->usineA->id,
            'is_active'  => true,
            // Aucun prix local
        ]);

        $prix = $produit->prixEffectifDansUsine($this->usineA->id);

        $this->assertEquals(1000, $prix['prix_achat'], 'Fallback sur prix_achat global');
        $this->assertEquals(1800, $prix['prix_vente'], 'Fallback sur prix_vente global');
        $this->assertEquals(900,  $prix['prix_usine'], 'Fallback sur prix_usine global');
        $this->assertNull($prix['tva'], 'TVA null si non définie');
    }

    public function test_prix_local_prend_priorite_sur_prix_global(): void
    {
        $produit = $this->creerProduitGlobal(prix_achat: 1000, prix_vente: 1800);

        ProduitSite::create([
            'produit_id' => $produit->id,
            'site_id'   => $this->usineA->id,
            'is_active'  => true,
            'prix_achat' => 750,
            'prix_vente' => 1200,
        ]);

        $prix = $produit->prixEffectifDansUsine($this->usineA->id);

        $this->assertEquals(750,  $prix['prix_achat'], 'Le prix local doit primer sur le global');
        $this->assertEquals(1200, $prix['prix_vente'], 'Le prix local doit primer sur le global');
    }

    public function test_prix_partiellement_surcharges_fallback_pour_le_reste(): void
    {
        $produit = $this->creerProduitGlobal(prix_achat: 1000, prix_vente: 1800, cout: 500);

        ProduitSite::create([
            'produit_id' => $produit->id,
            'site_id'   => $this->usineA->id,
            'is_active'  => true,
            'prix_vente' => 2000, // Seul prix_vente est surchargé
        ]);

        $prix = $produit->prixEffectifDansUsine($this->usineA->id);

        $this->assertEquals(1000, $prix['prix_achat'], 'prix_achat doit rester global');
        $this->assertEquals(2000, $prix['prix_vente'], 'prix_vente doit être local');
        $this->assertEquals(500,  $prix['cout'],       'cout doit rester global');
    }

    public function test_prix_effectif_null_si_pas_de_config_locale(): void
    {
        $produit = $this->creerProduitGlobal(prix_achat: 1000, prix_vente: 1800);

        // Aucune ligne produit_usines pour l'usine A
        $prix = $produit->prixEffectifDansUsine($this->usineA->id);

        // Pas de config locale → fallback global
        $this->assertEquals(1000, $prix['prix_achat']);
        $this->assertEquals(1800, $prix['prix_vente']);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 2. Prix différents entre usines
    // ──────────────────────────────────────────────────────────────────────

    public function test_usines_a_et_b_ont_des_prix_independants(): void
    {
        $produit = $this->creerProduitGlobal(prix_achat: 1000, prix_vente: 1500);

        ProduitSite::create([
            'produit_id' => $produit->id,
            'site_id'   => $this->usineA->id,
            'is_active'  => true,
            'prix_vente' => 1200, // Prix de vente spécial pour A
        ]);

        ProduitSite::create([
            'produit_id' => $produit->id,
            'site_id'   => $this->usineB->id,
            'is_active'  => true,
            'prix_vente' => 1700, // Prix de vente spécial pour B
        ]);

        $prixA = $produit->prixEffectifDansUsine($this->usineA->id);
        $prixB = $produit->prixEffectifDansUsine($this->usineB->id);

        $this->assertEquals(1200, $prixA['prix_vente'], 'Prix usine A');
        $this->assertEquals(1700, $prixB['prix_vente'], 'Prix usine B');
        $this->assertNotEquals($prixA['prix_vente'], $prixB['prix_vente']);
    }

    public function test_tva_local_independant_entre_usines(): void
    {
        $produit = $this->creerProduitGlobal(prix_vente: 5000);

        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineA->id, 'tva' => 18]);
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineB->id, 'tva' => 0]);

        $prixA = $produit->prixEffectifDansUsine($this->usineA->id);
        $prixB = $produit->prixEffectifDansUsine($this->usineB->id);

        $this->assertEquals(18, $prixA['tva'], 'TVA usine A = 18 %');
        $this->assertEquals(0,  $prixB['tva'], 'TVA usine B = 0 %');
    }

    // ──────────────────────────────────────────────────────────────────────
    // 3. Accesseurs sur ProduitUsine (prixVenteEffectif, etc.)
    // ──────────────────────────────────────────────────────────────────────

    public function test_prix_usine_effectif_retourne_local_si_defini(): void
    {
        $produit = $this->creerProduitGlobal(prix_usine: 800);

        $config = ProduitSite::create([
            'produit_id' => $produit->id,
            'site_id'   => $this->usineA->id,
            'is_active'  => true,
            'prix_usine' => 600,
        ]);
        $config->load('produit');

        $this->assertEquals(600, $config->prixUsineEffectif());
    }

    public function test_prix_vente_effectif_fallback_global_si_local_null(): void
    {
        $produit = $this->creerProduitGlobal(prix_vente: 2500);

        $config = ProduitSite::create([
            'produit_id' => $produit->id,
            'site_id'   => $this->usineA->id,
            'is_active'  => true,
            // prix_vente local non défini
        ]);
        $config->load('produit');

        $this->assertEquals(2500, $config->prixVenteEffectif());
    }

    public function test_cout_effectif_fallback_global(): void
    {
        $produit = $this->creerProduitGlobal(cout: 300);

        $config = ProduitSite::create([
            'produit_id' => $produit->id,
            'site_id'   => $this->usineA->id,
            'is_active'  => true,
        ]);
        $config->load('produit');

        $this->assertEquals(300, $config->coutEffectif());
    }

    // ──────────────────────────────────────────────────────────────────────
    // 4. Endpoint PATCH /produits/{id}/usines/{usine_id}/prix
    // ──────────────────────────────────────────────────────────────────────

    public function test_api_patch_prix_local_met_a_jour_uniquement_les_champs_fournis(): void
    {
        $produit = $this->creerProduitGlobal(prix_achat: 1000, prix_vente: 1500);
        ProduitSite::create([
            'produit_id' => $produit->id,
            'site_id'   => $this->usineA->id,
            'is_active'  => true,
            'prix_achat' => 800,
            'prix_vente' => 1200,
        ]);

        $user = $this->makeStaffWithPermission('produits.update');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Site-Id', (string) $this->usineA->id)
            ->patchJson("/api/v1/produits/{$produit->id}/usines/{$this->usineA->id}/prix", [
                'prix_vente' => 1900, // Uniquement prix_vente mis à jour
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('produit_sites', [
            'produit_id' => $produit->id,
            'site_id'   => $this->usineA->id,
            'prix_achat' => 800,  // inchangé
            'prix_vente' => 1900, // mis à jour
        ]);
    }

    public function test_api_patch_prix_sans_champ_retourne_422(): void
    {
        $produit = $this->creerProduitGlobal();
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineA->id, 'is_active' => true]);

        $user = $this->makeStaffWithPermission('produits.update');

        // Aucun champ fourni → withValidator doit rejeter
        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Site-Id', (string) $this->usineA->id)
            ->patchJson("/api/v1/produits/{$produit->id}/usines/{$this->usineA->id}/prix", []);

        $response->assertStatus(422);
    }

    public function test_api_patch_prix_retourne_prix_effectifs_dans_reponse(): void
    {
        $produit = $this->creerProduitGlobal(prix_vente: 2000);
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineA->id, 'is_active' => true]);

        $user = $this->makeStaffWithPermission('produits.update');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Site-Id', (string) $this->usineA->id)
            ->patchJson("/api/v1/produits/{$produit->id}/usines/{$this->usineA->id}/prix", [
                'prix_vente' => 2500,
                'tva'        => 18,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.prix_effectifs.prix_vente', 2500);
        $response->assertJsonPath('data.prix_effectifs.tva', 18);
    }

    public function test_api_patch_prix_usine_b_naffecte_pas_usine_a(): void
    {
        $produit = $this->creerProduitGlobal(prix_vente: 1000);
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineA->id, 'prix_vente' => 1000, 'is_active' => true]);
        ProduitSite::create(['produit_id' => $produit->id, 'site_id' => $this->usineB->id, 'prix_vente' => 1000, 'is_active' => true]);

        $user = $this->makeStaffWithPermission('produits.update');

        // Modifier le prix dans usine B
        $this->actingAs($user, 'sanctum')
            ->withHeader('X-Site-Id', (string) $this->usineB->id)
            ->patchJson("/api/v1/produits/{$produit->id}/usines/{$this->usineB->id}/prix", [
                'prix_vente' => 9999,
            ]);

        // Vérifier que usine A est inchangée
        $this->assertDatabaseHas('produit_sites', [
            'produit_id' => $produit->id,
            'site_id'   => $this->usineA->id,
            'prix_vente' => 1000,
        ]);
        $this->assertDatabaseHas('produit_sites', [
            'produit_id' => $produit->id,
            'site_id'   => $this->usineB->id,
            'prix_vente' => 9999,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function creerProduitGlobal(
        int $prix_achat = 1000,
        int $prix_vente = 1500,
        ?int $prix_usine = null,
        ?int $cout = null,
    ): Produit {
        $data = [
            'nom'        => 'Produit prix test ' . uniqid(),
            'code'       => substr(md5(uniqid('PRIX-')), 0, 12),
            'type'       => ProduitType::MATERIEL->value,
            'statut'     => ProduitStatut::ACTIF->value,
            'prix_achat' => $prix_achat,
            'prix_vente' => $prix_vente,
            'is_global'  => true,
            'site_id'    => null,
        ];
        if ($prix_usine !== null) {
            $data['prix_usine'] = $prix_usine;
        }
        if ($cout !== null) {
            $data['cout'] = $cout;
        }
        return Produit::withoutGlobalScopes()->create($data);
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
            'prenom'          => 'Prix',
            'phone'           => "+22462001{$counter}",
            'password'        => bcrypt('secret1234'),
            'pays'            => 'Guinée',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'ville'           => 'Conakry',
            'quartier'        => 'Kaloum',
        ]);

        $user->assignRole('admin_entreprise');
        $user->sites()->attach($this->usineA->id, ['role' => 'manager', 'is_default' => true]);
        $user->sites()->attach($this->usineB->id, ['role' => 'manager', 'is_default' => false]);
        return $user;
    }
}
