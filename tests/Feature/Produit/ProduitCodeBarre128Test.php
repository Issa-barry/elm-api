<?php

namespace Tests\Feature\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\SiteType;
use App\Models\Produit;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests Code128 barcode fields (code_interne, code_fournisseur).
 *
 * Couvre :
 *  - Création avec code_interne explicite
 *  - Auto-génération de code_interne depuis code (si absent)
 *  - Unicité de code_interne
 *  - Validation regex (ASCII imprimable 0x21–0x7E)
 *  - Lookup GET /by-code/{code}?mode=interne
 *  - Lookup GET /by-code/{code}?mode=fournisseur
 *  - Lookup GET /by-code/{code}?mode=auto (défaut)
 *  - 404 code introuvable
 *  - 409 code_fournisseur ambigu (plusieurs produits)
 *  - 422 mode invalide
 */
class ProduitCodeBarre128Test extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::firstOrCreate(
            ['code' => 'CB128-A'],
            ['nom' => 'Site CB128', 'type' => SiteType::USINE->value, 'statut' => 'active']
        );

        app(SiteContext::class)->setCurrentSiteId($this->site->id);

        $role = Role::findOrCreate('admin_entreprise', 'web');

        foreach (['produits.read', 'produits.create', 'produits.update'] as $perm) {
            $permission = Permission::findOrCreate($perm, 'web');
            $role->givePermissionTo($permission);
        }

        $this->user = User::create([
            'type'            => \App\Enums\UserType::STAFF->value,
            'nom'             => 'Test',
            'prenom'          => 'CB128',
            'phone'           => '+224620CB128',
            'password'        => bcrypt('secret1234'),
            'pays'            => 'Guinée',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'ville'           => 'Conakry',
            'default_site_id' => $this->site->id,
        ]);
        $this->user->assignRole('admin_entreprise');
        $this->user->sites()->attach($this->site->id, ['role' => 'manager', 'is_default' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function produitBase(array $overrides = []): array
    {
        static $seq = 0;
        $seq++;
        return array_merge([
            'nom'        => "Produit CB128-{$seq}",
            'type'       => ProduitType::MATERIEL->value,
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'prix_usine' => 900,
        ], $overrides);
    }

    private function createProduitDirect(array $attrs = []): Produit
    {
        static $seq = 0;
        $seq++;
        return Produit::withoutGlobalScopes()->create(array_merge([
            'nom'        => "Direct CB128-{$seq}",
            'code'       => 'CB128D' . str_pad($seq, 6, '0', STR_PAD_LEFT),
            'code_interne' => 'INTERNE-' . str_pad($seq, 6, '0', STR_PAD_LEFT),
            'type'       => ProduitType::MATERIEL->value,
            'statut'     => ProduitStatut::ACTIF->value,
            'prix_achat' => 1000,
            'site_id'    => $this->site->id,
        ], $attrs));
    }

    private function actingAsUser()
    {
        return $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Site-Id', (string) $this->site->id);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 1. Création
    // ──────────────────────────────────────────────────────────────────────

    public function test_creation_avec_code_interne_explicite(): void
    {
        $response = $this->actingAsUser()->postJson('/api/v1/produits', $this->produitBase([
            'code_interne' => 'SCAN-ABC123',
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('produits', ['code_interne' => 'SCAN-ABC123']);
    }

    public function test_code_interne_est_normalise_en_uppercase(): void
    {
        $response = $this->actingAsUser()->postJson('/api/v1/produits', $this->produitBase([
            'code_interne' => 'scan-abc123',
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('produits', ['code_interne' => 'SCAN-ABC123']);
    }

    public function test_code_interne_auto_genere_depuis_code(): void
    {
        $response = $this->actingAsUser()->postJson('/api/v1/produits', $this->produitBase([
            'code' => '202603120001',
        ]));

        $response->assertStatus(201);

        // code_interne doit être identique au code généré
        $id = $response->json('data.id');
        $produit = Produit::withTrashed()->find($id);
        $this->assertNotNull($produit->code_interne);
        $this->assertEquals($produit->code, $produit->code_interne);
    }

    public function test_code_interne_auto_genere_si_absent_et_code_absent(): void
    {
        // Ni code ni code_interne — les deux sont auto-générés
        $response = $this->actingAsUser()->postJson('/api/v1/produits', $this->produitBase());

        $response->assertStatus(201);

        $id = $response->json('data.id');
        $produit = Produit::withTrashed()->find($id);
        $this->assertNotNull($produit->code_interne);
        $this->assertEquals($produit->code, $produit->code_interne);
    }

    public function test_code_interne_unique(): void
    {
        $this->createProduitDirect(['code_interne' => 'UNIQUE-CODE']);

        $response = $this->actingAsUser()->postJson('/api/v1/produits', $this->produitBase([
            'code_interne' => 'UNIQUE-CODE',
        ]));

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_code_interne_regex_invalide(): void
    {
        // Espace (0x20) non autorisé — ASCII imprimable commence à 0x21
        $response = $this->actingAsUser()->postJson('/api/v1/produits', $this->produitBase([
            'code_interne' => 'CODE AVEC ESPACE',
        ]));

        $response->assertStatus(422);
    }

    public function test_code_fournisseur_optionnel_et_non_unique(): void
    {
        // Deux produits peuvent partager le même code_fournisseur
        $this->createProduitDirect(['code_fournisseur' => 'FOURN-SHARED']);

        $response = $this->actingAsUser()->postJson('/api/v1/produits', $this->produitBase([
            'code_fournisseur' => 'FOURN-SHARED',
        ]));

        $response->assertStatus(201);
        $this->assertEquals(2, Produit::where('code_fournisseur', 'FOURN-SHARED')->count());
    }

    // ──────────────────────────────────────────────────────────────────────
    // 2. Mise à jour
    // ──────────────────────────────────────────────────────────────────────

    public function test_update_code_interne(): void
    {
        $produit = $this->createProduitDirect();

        $response = $this->actingAsUser()->putJson("/api/v1/produits/{$produit->id}", [
            'nom'          => $produit->nom,
            'type'         => $produit->type->value,
            'code_interne' => 'NOUVEAU-INTERNE',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('produits', ['id' => $produit->id, 'code_interne' => 'NOUVEAU-INTERNE']);
    }

    public function test_update_code_interne_unique_ignore_self(): void
    {
        $produit = $this->createProduitDirect(['code_interne' => 'MON-CODE-INTERNE']);

        // Re-soumettre le même code_interne → doit passer (ignore himself)
        $response = $this->actingAsUser()->putJson("/api/v1/produits/{$produit->id}", [
            'nom'          => $produit->nom,
            'type'         => $produit->type->value,
            'code_interne' => 'MON-CODE-INTERNE',
        ]);

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 3. Lookup GET /by-code/{code}
    // ──────────────────────────────────────────────────────────────────────

    public function test_lookup_par_code_interne(): void
    {
        $produit = $this->createProduitDirect(['code_interne' => 'SCAN-LOOKUP-01']);

        $response = $this->actingAsUser()
            ->getJson('/api/v1/produits/by-code/SCAN-LOOKUP-01?mode=interne');

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $produit->id);
    }

    public function test_lookup_code_interne_case_insensitive(): void
    {
        $this->createProduitDirect(['code_interne' => 'SCAN-UPPER-01']);

        // Le contrôleur normalise en uppercase avant la recherche
        $response = $this->actingAsUser()
            ->getJson('/api/v1/produits/by-code/scan-upper-01?mode=interne');

        $response->assertStatus(200);
    }

    public function test_lookup_par_code_fournisseur_unique(): void
    {
        $produit = $this->createProduitDirect(['code_fournisseur' => 'EAN-FOURN-99']);

        $response = $this->actingAsUser()
            ->getJson('/api/v1/produits/by-code/EAN-FOURN-99?mode=fournisseur');

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $produit->id);
    }

    public function test_lookup_fournisseur_ambigu_retourne_409(): void
    {
        $this->createProduitDirect(['code_fournisseur' => 'SHARED-EAN']);
        $this->createProduitDirect(['code_fournisseur' => 'SHARED-EAN']);

        $response = $this->actingAsUser()
            ->getJson('/api/v1/produits/by-code/SHARED-EAN?mode=fournisseur');

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);
    }

    public function test_lookup_auto_priorite_interne(): void
    {
        // Même valeur dans les deux colonnes — interne doit être retourné
        $produit = $this->createProduitDirect([
            'code_interne'     => 'AUTO-CODE-X',
            'code_fournisseur' => 'AUTO-CODE-X',
        ]);
        // Deuxième produit avec même code_fournisseur → serait ambigu en mode fournisseur
        $this->createProduitDirect(['code_fournisseur' => 'AUTO-CODE-X']);

        $response = $this->actingAsUser()
            ->getJson('/api/v1/produits/by-code/AUTO-CODE-X');  // mode=auto par défaut

        // Doit trouver via interne (unique) sans déclencher 409
        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $produit->id);
    }

    public function test_lookup_auto_fallback_fournisseur(): void
    {
        $produit = $this->createProduitDirect([
            'code_interne'     => 'INTERNE-DIFF',
            'code_fournisseur' => 'FOURN-ONLY-99',
        ]);

        $response = $this->actingAsUser()
            ->getJson('/api/v1/produits/by-code/FOURN-ONLY-99');  // mode=auto

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $produit->id);
    }

    public function test_lookup_not_found_retourne_404(): void
    {
        $response = $this->actingAsUser()
            ->getJson('/api/v1/produits/by-code/CODE-INEXISTANT?mode=interne');

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    public function test_lookup_mode_invalide_retourne_422(): void
    {
        $response = $this->actingAsUser()
            ->getJson('/api/v1/produits/by-code/MONCODE?mode=mauvais');

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_lookup_sans_authentification_retourne_401(): void
    {
        $response = $this->withHeader('X-Site-Id', (string) $this->site->id)
            ->getJson('/api/v1/produits/by-code/MONCODE');

        $response->assertStatus(401);
    }
}
