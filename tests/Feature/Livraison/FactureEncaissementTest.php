<?php

namespace Tests\Feature\Livraison;

use App\Enums\UsineType;
use App\Models\FactureLivraison;
use App\Models\Livreur;
use App\Models\Proprietaire;
use App\Models\SortieVehicule;
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

    private User          $staff;
    private Usine         $usine;
    private SortieVehicule $sortieRetournee;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach (['factures-livraisons.create', 'factures-livraisons.read', 'encaissements.create', 'encaissements.read'] as $p) {
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
        $this->staff->givePermissionTo(['factures-livraisons.create', 'factures-livraisons.read', 'encaissements.create', 'encaissements.read']);

        $livreur      = Livreur::factory()->create();
        $proprietaire = Proprietaire::factory()->create();
        $vehicule     = Vehicule::withoutGlobalScopes()->create([
            'usine_id'                  => $this->usine->id,
            'nom_vehicule'              => 'Camion Facture',
            'immatriculation'           => 'FAC-001',
            'type_vehicule'             => 'camion',
            'capacite_packs'            => 100,
            'proprietaire_id'           => $proprietaire->id,
            'pris_en_charge_par_usine'  => false,
            'mode_commission'           => 'forfait',
            'valeur_commission'         => 300,
            'pourcentage_proprietaire'  => 60,
            'pourcentage_livreur'       => 40,
            'photo_path'                => 'vehicules/test.jpg',
            'is_active'                 => true,
        ]);

        $this->sortieRetournee = SortieVehicule::withoutGlobalScopes()->create([
            'usine_id'                          => $this->usine->id,
            'vehicule_id'                       => $vehicule->id,
            'livreur_id_effectif'               => $livreur->id,
            'packs_charges'                     => 100,
            'packs_retour'                      => 10,
            'date_depart'                       => now()->subDay(),
            'date_retour'                       => now(),
            'statut_sortie'                     => 'retourne',
            'snapshot_mode_commission'          => 'forfait',
            'snapshot_valeur_commission'        => 300,
            'snapshot_pourcentage_proprietaire' => 60,
            'snapshot_pourcentage_livreur'      => 40,
        ]);
    }

    // ─────────────────────────────────────────────────────
    //  Création facture depuis sortie retournée → 201
    // ─────────────────────────────────────────────────────
    public function test_creation_facture_depuis_sortie_retournee(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->postJson('/api/v1/factures-livraisons', [
                'sortie_vehicule_id' => $this->sortieRetournee->id,
                'montant_brut'       => 50000,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.montant_brut', '50000.00')
            ->assertJsonPath('data.statut_facture', 'emise');

        $this->assertStringStartsWith('FAC-LIV-', $response->json('data.reference'));
    }

    // ─────────────────────────────────────────────────────
    //  Double facture sur même sortie → 422
    // ─────────────────────────────────────────────────────
    public function test_double_facture_meme_sortie_retourne_422(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $this->withHeaders($header)->postJson('/api/v1/factures-livraisons', [
            'sortie_vehicule_id' => $this->sortieRetournee->id,
            'montant_brut'       => 50000,
        ])->assertCreated();

        $this->withHeaders($header)->postJson('/api/v1/factures-livraisons', [
            'sortie_vehicule_id' => $this->sortieRetournee->id,
            'montant_brut'       => 60000,
        ])->assertUnprocessable();
    }

    // ─────────────────────────────────────────────────────
    //  Encaissement OK → statut facture mis à jour
    // ─────────────────────────────────────────────────────
    public function test_encaissement_met_a_jour_statut_facture(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $factureId = $this->withHeaders($header)->postJson('/api/v1/factures-livraisons', [
            'sortie_vehicule_id' => $this->sortieRetournee->id,
            'montant_brut'       => 10000,
        ])->json('data.id');

        // Paiement partiel
        $this->withHeaders($header)->postJson('/api/v1/encaissements-livraisons', [
            'facture_livraison_id' => $factureId,
            'montant'              => 6000,
            'date_encaissement'    => now()->toDateString(),
            'mode_paiement'        => 'especes',
        ])->assertCreated();

        $facture = FactureLivraison::withoutGlobalScopes()->find($factureId);
        $this->assertEquals('partiellement_payee', $facture->statut_facture->value);

        // Solde total
        $this->withHeaders($header)->postJson('/api/v1/encaissements-livraisons', [
            'facture_livraison_id' => $factureId,
            'montant'              => 4000,
            'date_encaissement'    => now()->toDateString(),
            'mode_paiement'        => 'mobile_money',
        ])->assertCreated();

        $facture->refresh();
        $this->assertEquals('payee', $facture->statut_facture->value);
    }

    // ─────────────────────────────────────────────────────
    //  Dépassement montant facture → refusé
    // ─────────────────────────────────────────────────────
    public function test_encaissement_depasse_montant_facture_retourne_erreur(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $factureId = $this->withHeaders($header)->postJson('/api/v1/factures-livraisons', [
            'sortie_vehicule_id' => $this->sortieRetournee->id,
            'montant_brut'       => 5000,
        ])->json('data.id');

        $response = $this->withHeaders($header)->postJson('/api/v1/encaissements-livraisons', [
            'facture_livraison_id' => $factureId,
            'montant'              => 9999, // > 5000
            'date_encaissement'    => now()->toDateString(),
            'mode_paiement'        => 'virement',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['montant']);
    }
}
