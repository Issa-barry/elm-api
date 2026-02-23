<?php

namespace Tests\Feature\Vente;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\StatutFactureVente;
use App\Enums\UsineType;
use App\Models\CommandeVente;
use App\Models\FactureVente;
use App\Models\Livreur;
use App\Models\Produit;
use App\Models\Proprietaire;
use App\Models\Usine;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CommandeVenteTest extends TestCase
{
    use RefreshDatabase;

    private User    $staff;
    private Usine   $usine;
    private int     $vehiculeId;
    private Produit $produitFabricable;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach ([
            'commandes.create', 'commandes.read',
            'factures-livraisons.read',
        ] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $this->usine = Usine::create([
            'nom'    => 'Usine Commandes Test',
            'code'   => 'CMD-TEST',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        $this->staff = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usine->id,
        ]);
        $this->staff->usines()->attach($this->usine->id, ['role' => 'manager', 'is_default' => true]);
        $this->staff->givePermissionTo([
            'commandes.create', 'commandes.read',
            'factures-livraisons.read',
        ]);

        // Véhicule de test
        $proprietaire = Proprietaire::factory()->create();
        $livreur      = Livreur::factory()->create();

        $vehicule = Vehicule::withoutGlobalScopes()->create([
            'usine_id'                => $this->usine->id,
            'nom_vehicule'            => 'Camion CMD',
            'immatriculation'         => 'CMD-001',
            'type_vehicule'           => 'camion',
            'capacite_packs'          => 300,
            'proprietaire_id'         => $proprietaire->id,
            'livreur_principal_id'    => $livreur->id,
            'pris_en_charge_par_usine' => false,
            'is_active'               => true,
        ]);
        $this->vehiculeId = $vehicule->id;

        // Produit fabricable de test
        $this->produitFabricable = Produit::withoutGlobalScopes()->create([
            'usine_id'   => $this->usine->id,
            'nom'        => 'Béton préfabriqué',
            'code'       => 'BETON-01',
            'type'       => ProduitType::FABRICABLE->value,
            'statut'     => ProduitStatut::ACTIF->value,
            'prix_usine' => 5000,
            'prix_vente' => 8000,
            'qte_stock'  => 500,
        ]);
    }

    private function header(): array
    {
        return ['X-Usine-Id' => (string) $this->usine->id];
    }

    private function commandePayload(array $overrides = []): array
    {
        return array_merge([
            'vehicule_id' => $this->vehiculeId,
            'lignes'      => [
                [
                    'produit_id' => $this->produitFabricable->id,
                    'qte'        => 10,
                ],
            ],
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────
    //  1. Création commande → 201 + facture auto-créée (impayee)
    // ─────────────────────────────────────────────────────
    public function test_creation_commande_ok_avec_facture_auto_creee(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeaders($this->header())
            ->postJson('/api/v1/ventes/commandes', $this->commandePayload());

        $response->assertCreated()
            ->assertJsonPath('data.vehicule_id', $this->vehiculeId);

        $this->assertStringStartsWith('CMD-VNT-', $response->json('data.reference'));

        // La facture est automatiquement créée
        $factureData = $response->json('data.facture');
        $this->assertNotNull($factureData);
        $this->assertEquals('impayee', $factureData['statut_facture']);
        $this->assertStringStartsWith('FAC-VNT-', $factureData['reference']);

        // Un CommandeVente et une FactureVente en base
        $this->assertEquals(1, CommandeVente::withoutGlobalScopes()->count());
        $this->assertEquals(1, FactureVente::withoutGlobalScopes()->count());
    }

    // ─────────────────────────────────────────────────────
    //  2. Calcul total commande correct
    // ─────────────────────────────────────────────────────
    public function test_calcul_total_commande_correct(): void
    {
        Sanctum::actingAs($this->staff);

        // 10 unités × prix_vente 8000 = 80 000
        $response = $this->withHeaders($this->header())
            ->postJson('/api/v1/ventes/commandes', $this->commandePayload(['lignes' => [
                ['produit_id' => $this->produitFabricable->id, 'qte' => 10],
            ]]));

        $response->assertCreated();

        $total = (float) $response->json('data.total_commande');
        $this->assertEquals(80000.0, $total);

        // La facture reflète le total
        $montantBrut = (float) $response->json('data.facture.montant_brut');
        $montantNet  = (float) $response->json('data.facture.montant_net');
        $this->assertEquals(80000.0, $montantBrut);
        $this->assertEquals(80000.0, $montantNet);
    }

    // ─────────────────────────────────────────────────────
    //  3. Plusieurs lignes → somme correcte
    // ─────────────────────────────────────────────────────
    public function test_calcul_total_multi_lignes(): void
    {
        Sanctum::actingAs($this->staff);

        // Deuxième produit fabricable
        $produit2 = Produit::withoutGlobalScopes()->create([
            'usine_id'   => $this->usine->id,
            'nom'        => 'Brique standard',
            'code'       => 'BRIQUE-01',
            'type'       => ProduitType::FABRICABLE->value,
            'statut'     => ProduitStatut::ACTIF->value,
            'prix_usine' => 200,
            'prix_vente' => 300,
            'qte_stock'  => 1000,
        ]);

        // Ligne 1 : 10 × 8000 = 80 000 ; Ligne 2 : 50 × 300 = 15 000 ; total = 95 000
        $response = $this->withHeaders($this->header())
            ->postJson('/api/v1/ventes/commandes', [
                'vehicule_id' => $this->vehiculeId,
                'lignes' => [
                    ['produit_id' => $this->produitFabricable->id, 'qte' => 10],
                    ['produit_id' => $produit2->id,               'qte' => 50],
                ],
            ]);

        $response->assertCreated();
        $this->assertEquals(95000.0, (float) $response->json('data.total_commande'));
    }

    // ─────────────────────────────────────────────────────
    //  4. Refus produit non fabricable → 422
    // ─────────────────────────────────────────────────────
    public function test_refus_produit_non_fabricable(): void
    {
        Sanctum::actingAs($this->staff);

        $produitMateriel = Produit::withoutGlobalScopes()->create([
            'usine_id'   => $this->usine->id,
            'nom'        => 'Pelle',
            'code'       => 'PELLE-01',
            'type'       => ProduitType::MATERIEL->value,
            'statut'     => ProduitStatut::ACTIF->value,
            'prix_achat' => 2000,
            'qte_stock'  => 10,
        ]);

        $response = $this->withHeaders($this->header())
            ->postJson('/api/v1/ventes/commandes', [
                'vehicule_id' => $this->vehiculeId,
                'lignes'      => [['produit_id' => $produitMateriel->id, 'qte' => 5]],
            ]);

        $response->assertUnprocessable();
    }

    // ─────────────────────────────────────────────────────
    //  5. Refus qte invalide → 422
    // ─────────────────────────────────────────────────────
    public function test_refus_qte_invalide(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeaders($this->header())
            ->postJson('/api/v1/ventes/commandes', [
                'vehicule_id' => $this->vehiculeId,
                'lignes'      => [['produit_id' => $this->produitFabricable->id, 'qte' => 0]],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['lignes.0.qte']);
    }

    // ─────────────────────────────────────────────────────
    //  6. Refus sans lignes → 422
    // ─────────────────────────────────────────────────────
    public function test_refus_commande_sans_lignes(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeaders($this->header())
            ->postJson('/api/v1/ventes/commandes', [
                'vehicule_id' => $this->vehiculeId,
                'lignes'      => [],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['lignes']);
    }

    // ─────────────────────────────────────────────────────
    //  7. Liste commandes → 200
    // ─────────────────────────────────────────────────────
    public function test_liste_commandes(): void
    {
        Sanctum::actingAs($this->staff);

        $this->withHeaders($this->header())
            ->postJson('/api/v1/ventes/commandes', $this->commandePayload())
            ->assertCreated();

        $this->withHeaders($this->header())
            ->getJson('/api/v1/ventes/commandes')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // ─────────────────────────────────────────────────────
    //  8. Détail commande → 200 avec lignes + facture
    // ─────────────────────────────────────────────────────
    public function test_detail_commande_avec_lignes_et_facture(): void
    {
        Sanctum::actingAs($this->staff);

        $commandeId = $this->withHeaders($this->header())
            ->postJson('/api/v1/ventes/commandes', $this->commandePayload())
            ->json('data.id');

        $response = $this->withHeaders($this->header())
            ->getJson("/api/v1/ventes/commandes/{$commandeId}");

        $response->assertOk()
            ->assertJsonPath('data.id', $commandeId);

        $this->assertNotEmpty($response->json('data.lignes'));
        $this->assertNotNull($response->json('data.facture'));
        $this->assertEquals('impayee', $response->json('data.facture.statut_facture'));
    }

    // ─────────────────────────────────────────────────────
    //  9. Snapshots de prix capturés au moment de la commande
    // ─────────────────────────────────────────────────────
    public function test_snapshots_prix_captures_a_la_creation(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeaders($this->header())
            ->postJson('/api/v1/ventes/commandes', $this->commandePayload());

        $response->assertCreated();

        $ligne = $response->json('data.lignes.0');
        $this->assertEquals('5000.00', $ligne['prix_usine_snapshot']);
        $this->assertEquals('8000.00', $ligne['prix_vente_snapshot']);
        $this->assertEquals('80000.00', $ligne['total_ligne']); // 10 × 8000
    }
}
