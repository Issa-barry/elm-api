<?php

namespace Tests\Feature\Vente;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Enums\StatutFactureVente;
use App\Enums\UsineType;
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

class FactureVenteTest extends TestCase
{
    use RefreshDatabase;

    private User  $staff;
    private Usine $usine;
    private int   $vehiculeId;
    private int   $produitId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach ([
            'commandes.create', 'commandes.read',
            'factures-livraisons.read', 'factures-livraisons.update',
            'encaissements.create', 'encaissements.read',
        ] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $this->usine = Usine::create([
            'nom'    => 'Usine Facture Vente Test',
            'code'   => 'FVT-TEST',
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
            'factures-livraisons.read', 'factures-livraisons.update',
            'encaissements.create', 'encaissements.read',
        ]);

        $proprietaire = Proprietaire::factory()->create();
        $livreur      = Livreur::factory()->create();

        $vehicule = Vehicule::withoutGlobalScopes()->create([
            'usine_id'                => $this->usine->id,
            'nom_vehicule'            => 'Camion FVT',
            'immatriculation'         => 'FVT-001',
            'type_vehicule'           => 'camion',
            'capacite_packs'          => 300,
            'proprietaire_id'         => $proprietaire->id,
            'livreur_principal_id'    => $livreur->id,
            'pris_en_charge_par_usine' => false,
            'is_active'               => true,
        ]);
        $this->vehiculeId = $vehicule->id;

        $produit = Produit::withoutGlobalScopes()->create([
            'usine_id'   => $this->usine->id,
            'nom'        => 'Béton FVT',
            'code'       => 'BETON-FVT',
            'type'       => ProduitType::FABRICABLE->value,
            'statut'     => ProduitStatut::ACTIF->value,
            'prix_usine' => 1000,
            'prix_vente' => 2000,
            'qte_stock'  => 1000,
        ]);
        $this->produitId = $produit->id;
    }

    private function header(): array
    {
        return ['X-Usine-Id' => (string) $this->usine->id];
    }

    /**
     * Crée une commande et retourne l'id de la facture auto-créée.
     */
    private function creerFactureViaCommande(int $qte = 10): int
    {
        $response = $this->withHeaders($this->header())
            ->postJson('/api/v1/ventes/commandes', [
                'vehicule_id' => $this->vehiculeId,
                'lignes'      => [['produit_id' => $this->produitId, 'qte' => $qte]],
            ]);

        $response->assertCreated();

        return (int) $response->json('data.facture.id');
    }

    // ─────────────────────────────────────────────────────
    //  1. Facture auto-créée → statut impayee
    // ─────────────────────────────────────────────────────
    public function test_facture_auto_creee_statut_impayee(): void
    {
        Sanctum::actingAs($this->staff);

        $factureId = $this->creerFactureViaCommande(5);

        $facture = FactureVente::withoutGlobalScopes()->find($factureId);
        $this->assertNotNull($facture);
        $this->assertEquals(StatutFactureVente::IMPAYEE, $facture->statut_facture);
        $this->assertEquals(10000.0, (float) $facture->montant_brut); // 5 × 2000
        $this->assertStringStartsWith('FAC-VNT-', $facture->reference);
    }

    // ─────────────────────────────────────────────────────
    //  2. Encaissement partiel → statut partiel, puis complet → payee
    // ─────────────────────────────────────────────────────
    public function test_encaissement_partiel_puis_complet(): void
    {
        Sanctum::actingAs($this->staff);

        // qte 10 × 2000 = 20 000
        $factureId = $this->creerFactureViaCommande(10);

        // Paiement partiel
        $this->withHeaders($this->header())->postJson('/api/v1/ventes/encaissements', [
            'facture_vente_id'  => $factureId,
            'montant'           => 12000,
            'date_encaissement' => now()->toDateString(),
            'mode_paiement'     => 'especes',
        ])->assertCreated();

        $facture = FactureVente::withoutGlobalScopes()->find($factureId);
        $this->assertEquals(StatutFactureVente::PARTIEL, $facture->statut_facture);

        // Solde
        $this->withHeaders($this->header())->postJson('/api/v1/ventes/encaissements', [
            'facture_vente_id'  => $factureId,
            'montant'           => 8000,
            'date_encaissement' => now()->toDateString(),
            'mode_paiement'     => 'mobile_money',
        ])->assertCreated();

        $facture->refresh();
        $this->assertEquals(StatutFactureVente::PAYEE, $facture->statut_facture);
    }

    // ─────────────────────────────────────────────────────
    //  3. Dépassement montant facture → 422
    // ─────────────────────────────────────────────────────
    public function test_encaissement_depasse_montant_retourne_422(): void
    {
        Sanctum::actingAs($this->staff);

        // 5 × 2000 = 10 000
        $factureId = $this->creerFactureViaCommande(5);

        $this->withHeaders($this->header())->postJson('/api/v1/ventes/encaissements', [
            'facture_vente_id'  => $factureId,
            'montant'           => 15000,
            'date_encaissement' => now()->toDateString(),
            'mode_paiement'     => 'especes',
        ])->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────
    //  4. Annulation facture impayee → statut annulea
    // ─────────────────────────────────────────────────────
    public function test_annulation_facture_impayee(): void
    {
        Sanctum::actingAs($this->staff);

        $factureId = $this->creerFactureViaCommande();

        $this->withHeaders($this->header())
            ->postJson("/api/v1/ventes/factures/{$factureId}/annuler")
            ->assertOk()
            ->assertJsonPath('data.statut_facture', 'annulee');
    }

    // ─────────────────────────────────────────────────────
    //  5. Annulation facture payée → 422
    // ─────────────────────────────────────────────────────
    public function test_annulation_facture_payee_impossible(): void
    {
        Sanctum::actingAs($this->staff);

        // 5 × 2000 = 10 000
        $factureId = $this->creerFactureViaCommande(5);

        $this->withHeaders($this->header())->postJson('/api/v1/ventes/encaissements', [
            'facture_vente_id'  => $factureId,
            'montant'           => 10000,
            'date_encaissement' => now()->toDateString(),
            'mode_paiement'     => 'especes',
        ])->assertCreated();

        $this->withHeaders($this->header())
            ->postJson("/api/v1/ventes/factures/{$factureId}/annuler")
            ->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────
    //  6. Encaissement sur facture annulée → 422
    // ─────────────────────────────────────────────────────
    public function test_encaissement_sur_facture_annulea_bloque(): void
    {
        Sanctum::actingAs($this->staff);

        $factureId = $this->creerFactureViaCommande();

        $this->withHeaders($this->header())
            ->postJson("/api/v1/ventes/factures/{$factureId}/annuler")
            ->assertOk();

        $this->withHeaders($this->header())->postJson('/api/v1/ventes/encaissements', [
            'facture_vente_id'  => $factureId,
            'montant'           => 5000,
            'date_encaissement' => now()->toDateString(),
            'mode_paiement'     => 'especes',
        ])->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────
    //  7. Liste et détail factures
    // ─────────────────────────────────────────────────────
    public function test_liste_et_detail_factures(): void
    {
        Sanctum::actingAs($this->staff);

        $factureId = $this->creerFactureViaCommande();

        $this->withHeaders($this->header())
            ->getJson('/api/v1/ventes/factures')
            ->assertOk()
            ->assertJsonPath('data.data.0.statut_facture', 'impayee');

        $this->withHeaders($this->header())
            ->getJson("/api/v1/ventes/factures/{$factureId}")
            ->assertOk()
            ->assertJsonPath('data.id', $factureId);
    }
}
