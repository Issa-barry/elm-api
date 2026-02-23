<?php

namespace Tests\Feature\Livraison;

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

class FactureEncaissementTest extends TestCase
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
            'commandes.create',
            'factures-livraisons.read',
            'encaissements.create', 'encaissements.read',
        ] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $this->usine = Usine::create([
            'nom'    => 'Usine Facture Test',
            'code'   => 'FAC-TEST',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        $this->staff = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usine->id,
        ]);
        $this->staff->usines()->attach($this->usine->id, ['role' => 'manager', 'is_default' => true]);
        $this->staff->givePermissionTo([
            'commandes.create',
            'factures-livraisons.read',
            'encaissements.create', 'encaissements.read',
        ]);

        $proprietaire = Proprietaire::factory()->create();
        Livreur::factory()->create();

        $vehicule = Vehicule::withoutGlobalScopes()->create([
            'usine_id'                => $this->usine->id,
            'nom_vehicule'            => 'Camion Facture',
            'immatriculation'         => 'FAC-001',
            'type_vehicule'           => 'camion',
            'capacite_packs'          => 100,
            'proprietaire_id'         => $proprietaire->id,
            'pris_en_charge_par_usine' => false,
            'is_active'               => true,
        ]);
        $this->vehiculeId = $vehicule->id;

        $produit = Produit::withoutGlobalScopes()->create([
            'usine_id'   => $this->usine->id,
            'nom'        => 'Béton Facture',
            'code'       => 'BETON-FAC',
            'type'       => ProduitType::FABRICABLE->value,
            'statut'     => ProduitStatut::ACTIF->value,
            'prix_usine' => 400,
            'prix_vente' => 500,
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
    private function creerFacture(int $qte = 100): int
    {
        return (int) $this->withHeaders($this->header())
            ->postJson('/api/v1/ventes/commandes', [
                'vehicule_id' => $this->vehiculeId,
                'lignes'      => [['produit_id' => $this->produitId, 'qte' => $qte]],
            ])
            ->json('data.facture.id');
    }

    // ─────────────────────────────────────────────────────
    //  Création facture depuis commande → 201
    // ─────────────────────────────────────────────────────
    public function test_creation_facture_depuis_commande(): void
    {
        Sanctum::actingAs($this->staff);

        // 100 × 500 = 50 000
        $factureId = $this->creerFacture(100);
        $this->assertGreaterThan(0, $factureId);

        $facture = FactureVente::withoutGlobalScopes()->find($factureId);
        $this->assertEquals('50000.00', $facture->montant_brut);
        $this->assertEquals(StatutFactureVente::IMPAYEE, $facture->statut_facture);
        $this->assertStringStartsWith('FAC-VNT-', $facture->reference);
    }

    // ─────────────────────────────────────────────────────
    //  Plusieurs commandes → plusieurs factures permises
    // ─────────────────────────────────────────────────────
    public function test_plusieurs_factures_par_vehicule_sont_permises(): void
    {
        Sanctum::actingAs($this->staff);

        $this->withHeaders($this->header())->postJson('/api/v1/ventes/commandes', [
            'vehicule_id' => $this->vehiculeId,
            'lignes'      => [['produit_id' => $this->produitId, 'qte' => 50]],
        ])->assertCreated();

        $this->withHeaders($this->header())->postJson('/api/v1/ventes/commandes', [
            'vehicule_id' => $this->vehiculeId,
            'lignes'      => [['produit_id' => $this->produitId, 'qte' => 60]],
        ])->assertCreated();

        $this->assertEquals(2, FactureVente::withoutGlobalScopes()->count());
    }

    // ─────────────────────────────────────────────────────
    //  Encaissement OK → statut facture mis à jour
    // ─────────────────────────────────────────────────────
    public function test_encaissement_met_a_jour_statut_facture(): void
    {
        Sanctum::actingAs($this->staff);

        // 20 × 500 = 10 000
        $factureId = $this->creerFacture(20);

        // Paiement partiel
        $this->withHeaders($this->header())->postJson('/api/v1/ventes/encaissements', [
            'facture_vente_id'  => $factureId,
            'montant'           => 6000,
            'date_encaissement' => now()->toDateString(),
            'mode_paiement'     => 'especes',
        ])->assertCreated();

        $facture = FactureVente::withoutGlobalScopes()->find($factureId);
        $this->assertEquals(StatutFactureVente::PARTIEL, $facture->statut_facture);

        // Solde total
        $this->withHeaders($this->header())->postJson('/api/v1/ventes/encaissements', [
            'facture_vente_id'  => $factureId,
            'montant'           => 4000,
            'date_encaissement' => now()->toDateString(),
            'mode_paiement'     => 'mobile_money',
        ])->assertCreated();

        $facture->refresh();
        $this->assertEquals(StatutFactureVente::PAYEE, $facture->statut_facture);
    }

    // ─────────────────────────────────────────────────────
    //  Dépassement montant facture → refusé
    // ─────────────────────────────────────────────────────
    public function test_encaissement_depasse_montant_facture_retourne_erreur(): void
    {
        Sanctum::actingAs($this->staff);

        // 10 × 500 = 5 000
        $factureId = $this->creerFacture(10);

        $response = $this->withHeaders($this->header())->postJson('/api/v1/ventes/encaissements', [
            'facture_vente_id'  => $factureId,
            'montant'           => 9999,
            'date_encaissement' => now()->toDateString(),
            'mode_paiement'     => 'virement',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['montant']);
    }
}
