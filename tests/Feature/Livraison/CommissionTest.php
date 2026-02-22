<?php

namespace Tests\Feature\Livraison;

use App\Enums\UsineType;
use App\Models\DeductionCommission;
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

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    private User          $staff;
    private Usine         $usine;
    private SortieVehicule $sortie;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach (['commissions.read', 'commissions.create', 'factures-livraisons.create'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $this->usine = Usine::create([
            'nom'    => 'Usine Commission Test',
            'code'   => 'COM-TEST',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        $this->staff = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usine->id,
        ]);
        $this->staff->usines()->attach($this->usine->id, ['role' => 'manager', 'is_default' => true]);
        $this->staff->givePermissionTo(['commissions.read', 'commissions.create', 'factures-livraisons.create']);

        $livreur      = Livreur::factory()->create();
        $proprietaire = Proprietaire::factory()->create();
        $vehicule     = Vehicule::withoutGlobalScopes()->create([
            'usine_id'                  => $this->usine->id,
            'nom_vehicule'              => 'Camion Commission',
            'immatriculation'           => 'COM-001',
            'type_vehicule'             => 'camion',
            'capacite_packs'            => 100,
            'proprietaire_id'           => $proprietaire->id,
            'pris_en_charge_par_usine'  => false,
            'mode_commission'           => 'forfait',
            'valeur_commission'         => 1000, // 1000 par pack livré
            'pourcentage_proprietaire'  => 60,
            'pourcentage_livreur'       => 40,
            'photo_path'                => 'vehicules/test.jpg',
            'is_active'                 => true,
        ]);

        // Sortie clôturée : 90 packs livrés (100 chargés - 10 retournés)
        $this->sortie = SortieVehicule::withoutGlobalScopes()->create([
            'usine_id'                          => $this->usine->id,
            'vehicule_id'                       => $vehicule->id,
            'livreur_id_effectif'               => $livreur->id,
            'packs_charges'                     => 100,
            'packs_retour'                      => 10,
            'date_depart'                       => now()->subDay(),
            'date_retour'                       => now(),
            'statut_sortie'                     => 'cloture',
            'snapshot_mode_commission'          => 'forfait',
            'snapshot_valeur_commission'        => 1000,
            'snapshot_pourcentage_proprietaire' => 60,
            'snapshot_pourcentage_livreur'      => 40,
        ]);
    }

    // ─────────────────────────────────────────────────────
    //  Calcul commission brute/nette sans déductions
    // ─────────────────────────────────────────────────────
    public function test_calcul_commission_brute_sans_deductions(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->getJson("/api/v1/commissions/{$this->sortie->id}/calcul");

        $response->assertOk()
            ->assertJsonPath('data.packs_livres', 90)
            ->assertJson(['data' => ['commission_brute_totale' => 90000]])
            ->assertJson(['data' => ['part_proprietaire_brute' => 54000]])
            ->assertJson(['data' => ['part_livreur_brute'      => 36000]])
            ->assertJson(['data' => ['part_proprietaire_nette' => 54000]])
            ->assertJson(['data' => ['part_livreur_nette'      => 36000]]);
    }

    // ─────────────────────────────────────────────────────
    //  Calcul avec déductions
    // ─────────────────────────────────────────────────────
    public function test_calcul_commission_avec_deductions(): void
    {
        // Déduction carburant sur propriétaire : 5000
        DeductionCommission::create([
            'sortie_vehicule_id' => $this->sortie->id,
            'cible'              => 'proprietaire',
            'type_deduction'     => 'carburant',
            'montant'            => 5000,
        ]);

        // Déduction avance sur livreur : 2000
        DeductionCommission::create([
            'sortie_vehicule_id' => $this->sortie->id,
            'cible'              => 'livreur',
            'type_deduction'     => 'avance',
            'montant'            => 2000,
        ]);

        Sanctum::actingAs($this->staff);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->getJson("/api/v1/commissions/{$this->sortie->id}/calcul");

        $response->assertOk()
            ->assertJson(['data' => ['deductions_proprietaire' => 5000]])
            ->assertJson(['data' => ['deductions_livreur'      => 2000]])
            ->assertJson(['data' => ['part_proprietaire_nette' => 49000]])
            ->assertJson(['data' => ['part_livreur_nette'      => 34000]]);
    }

    // ─────────────────────────────────────────────────────
    //  Paiement commission sur sortie clôturée → 201
    // ─────────────────────────────────────────────────────
    public function test_paiement_commission_sortie_cloturee(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->postJson("/api/v1/commissions/{$this->sortie->id}/paiement", [
                'date_paiement' => now()->toDateString(),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.statut', 'paye')
            ->assertJsonPath('data.commission_brute_totale', '90000.00')
            ->assertJsonPath('data.part_proprietaire_nette', '54000.00')
            ->assertJsonPath('data.part_livreur_nette', '36000.00');
    }

    // ─────────────────────────────────────────────────────
    //  Paiement double → 409
    // ─────────────────────────────────────────────────────
    public function test_paiement_commission_double_retourne_409(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $this->withHeaders($header)->postJson("/api/v1/commissions/{$this->sortie->id}/paiement")->assertCreated();
        $this->withHeaders($header)->postJson("/api/v1/commissions/{$this->sortie->id}/paiement")->assertStatus(409);
    }

    // ─────────────────────────────────────────────────────
    //  Paiement sur sortie non clôturée → 422
    // ─────────────────────────────────────────────────────
    public function test_paiement_commission_sortie_non_cloturee_retourne_422(): void
    {
        $sortieEnCours = SortieVehicule::withoutGlobalScopes()->create([
            'usine_id'                          => $this->usine->id,
            'vehicule_id'                       => $this->sortie->vehicule_id,
            'livreur_id_effectif'               => $this->sortie->livreur_id_effectif,
            'packs_charges'                     => 50,
            'date_depart'                       => now(),
            'statut_sortie'                     => 'en_cours',
            'snapshot_mode_commission'          => 'forfait',
            'snapshot_valeur_commission'        => 500,
            'snapshot_pourcentage_proprietaire' => 60,
            'snapshot_pourcentage_livreur'      => 40,
        ]);

        Sanctum::actingAs($this->staff);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->postJson("/api/v1/commissions/{$sortieEnCours->id}/paiement");

        $response->assertStatus(422);
    }
}
