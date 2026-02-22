<?php

namespace Tests\Feature\Livraison;

use App\Enums\UsineType;
use App\Models\Livreur;
use App\Models\Proprietaire;
use App\Models\Usine;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowSimplifieTest extends TestCase
{
    use RefreshDatabase;

    private User  $staff;
    private Usine $usine;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach ([
            'vehicules.create', 'vehicules.read',
            'factures-livraisons.create', 'factures-livraisons.read',
            'encaissements.create', 'encaissements.read',
            'commissions.create', 'commissions.read',
        ] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $this->usine = Usine::create([
            'nom'    => 'Usine Workflow Simplifié',
            'code'   => 'WFS-TEST',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        $this->staff = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usine->id,
        ]);
        $this->staff->usines()->attach($this->usine->id, ['role' => 'manager', 'is_default' => true]);
        $this->staff->givePermissionTo([
            'vehicules.create', 'vehicules.read',
            'factures-livraisons.create', 'factures-livraisons.read',
            'encaissements.create', 'encaissements.read',
            'commissions.create', 'commissions.read',
        ]);
    }

    // ─────────────────────────────────────────────────────
    //  Payload one-shot helper
    // ─────────────────────────────────────────────────────
    private function oneShotPayload(array $overrides = []): array
    {
        return array_merge([
            'vehicule'     => [
                'nom_vehicule'             => 'Camion WFS',
                'immatriculation'          => 'WFS-001',
                'type_vehicule'            => 'camion',
                'capacite_packs'           => 200,
                'mode_commission'          => 'forfait',
                'valeur_commission'        => 1000,
                'pourcentage_proprietaire' => 60,
                'pourcentage_livreur'      => 40,
            ],
            'proprietaire' => [
                'nom'      => 'DIALLO',
                'prenom'   => 'Mamadou',
                'phone'    => '+224620000100',
                'pays'     => 'Guinée',
                'ville'    => 'Conakry',
                'quartier' => 'Matam',
            ],
            'livreur'      => [
                'nom'    => 'BALDE',
                'prenom' => 'Alpha',
                'phone'  => '+224621000100',
            ],
            'photo'        => UploadedFile::fake()->image('camion.jpg', 800, 600),
        ], $overrides);
    }

    // ─────────────────────────────────────────────────────
    //  1. Création one-shot → 201
    // ─────────────────────────────────────────────────────
    public function test_creation_one_shot_vehicule_proprietaire_livreur(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->postJson('/api/v1/livraisons/one-shot', $this->oneShotPayload());

        $response->assertCreated()
            ->assertJsonPath('data.vehicule.nom_vehicule', 'Camion WFS')
            ->assertJsonPath('data.vehicule.immatriculation', 'WFS-001')
            ->assertJsonPath('data.proprietaire.nom', 'DIALLO')
            ->assertJsonPath('data.livreur.nom', 'BALDE');

        $photoPath = $response->json('data.vehicule.photo_path');
        Storage::disk('public')->assertExists($photoPath);
    }

    // ─────────────────────────────────────────────────────
    //  2. Phone identique → réutilise l'enregistrement
    // ─────────────────────────────────────────────────────
    public function test_one_shot_reutilise_proprietaire_et_livreur_existants(): void
    {
        Proprietaire::create([
            'nom'      => 'CAMARA',
            'prenom'   => 'Ibrahim',
            'phone'    => '+224620000200',
            'pays'     => 'Guinée',
            'ville'    => 'Conakry',
            'quartier' => 'Matam',
        ]);
        Livreur::create([
            'nom'    => 'SOW',
            'prenom' => 'Ousmane',
            'phone'  => '+224621000200',
        ]);

        Sanctum::actingAs($this->staff);

        $payload = $this->oneShotPayload([
            'vehicule'     => array_merge($this->oneShotPayload()['vehicule'], ['immatriculation' => 'WFS-002']),
            'proprietaire' => ['nom' => 'DIALLO', 'prenom' => 'X', 'phone' => '+224620000200'],
            'livreur'      => ['nom' => 'BALDE', 'prenom' => 'Y', 'phone' => '+224621000200'],
        ]);

        $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->postJson('/api/v1/livraisons/one-shot', $payload)
            ->assertCreated();

        $this->assertEquals(1, Proprietaire::count(), 'Le propriétaire existant doit être réutilisé');
        $this->assertEquals(1, Livreur::count(), 'Le livreur existant doit être réutilisé');
    }

    // ─────────────────────────────────────────────────────
    //  3. Création facture liée au véhicule → 201 + snapshots
    // ─────────────────────────────────────────────────────
    public function test_creation_facture_simplifiee_avec_snapshots(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        // Créer le véhicule via one-shot
        $vehiculeId = $this->withHeaders($header)
            ->postJson('/api/v1/livraisons/one-shot', $this->oneShotPayload())
            ->json('data.vehicule.id');

        // Créer la facture
        $response = $this->withHeaders($header)
            ->postJson('/api/v1/livraisons/factures', [
                'vehicule_id'  => $vehiculeId,
                'packs_charges' => 150,
                'montant_brut'  => 75000,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.packs_charges', 150)
            ->assertJsonPath('data.snapshot_mode_commission', 'forfait')
            ->assertJsonPath('data.statut_facture', 'emise');

        $this->assertStringStartsWith('FAC-LIV-', $response->json('data.reference'));
        $this->assertEquals(1000, $response->json('data.snapshot_valeur_commission'));
        $this->assertEquals(60, $response->json('data.snapshot_pourcentage_proprietaire'));
        $this->assertEquals(40, $response->json('data.snapshot_pourcentage_livreur'));
    }

    // ─────────────────────────────────────────────────────
    //  4. Encaissement partiel → partiellement_payee → payee
    // ─────────────────────────────────────────────────────
    public function test_encaissement_partiel_puis_complet_workflow_simplifie(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $vehiculeId = $this->withHeaders($header)
            ->postJson('/api/v1/livraisons/one-shot', $this->oneShotPayload())
            ->json('data.vehicule.id');

        $factureId = $this->withHeaders($header)
            ->postJson('/api/v1/livraisons/factures', [
                'vehicule_id'  => $vehiculeId,
                'packs_charges' => 100,
                'montant_brut'  => 20000,
            ])->json('data.id');

        // Paiement partiel
        $this->withHeaders($header)->postJson('/api/v1/encaissements-livraisons', [
            'facture_livraison_id' => $factureId,
            'montant'              => 12000,
            'date_encaissement'    => now()->toDateString(),
            'mode_paiement'        => 'especes',
        ])->assertCreated();

        $facture = \App\Models\FactureLivraison::withoutGlobalScopes()->find($factureId);
        $this->assertEquals('partiellement_payee', $facture->statut_facture->value);

        // Solde
        $this->withHeaders($header)->postJson('/api/v1/encaissements-livraisons', [
            'facture_livraison_id' => $factureId,
            'montant'              => 8000,
            'date_encaissement'    => now()->toDateString(),
            'mode_paiement'        => 'mobile_money',
        ])->assertCreated();

        $facture->refresh();
        $this->assertEquals('payee', $facture->statut_facture->value);
    }

    // ─────────────────────────────────────────────────────
    //  5. Paiement commission refusé si facture non payée
    // ─────────────────────────────────────────────────────
    public function test_paiement_commission_refuse_si_facture_non_payee(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $vehiculeId = $this->withHeaders($header)
            ->postJson('/api/v1/livraisons/one-shot', $this->oneShotPayload())
            ->json('data.vehicule.id');

        $factureId = $this->withHeaders($header)
            ->postJson('/api/v1/livraisons/factures', [
                'vehicule_id'  => $vehiculeId,
                'packs_charges' => 100,
                'montant_brut'  => 30000,
            ])->json('data.id');

        // Encaissement partiel seulement
        $this->withHeaders($header)->postJson('/api/v1/encaissements-livraisons', [
            'facture_livraison_id' => $factureId,
            'montant'              => 10000,
            'date_encaissement'    => now()->toDateString(),
            'mode_paiement'        => 'especes',
        ])->assertCreated();

        // Tentative de paiement commission → 422
        $this->withHeaders($header)
            ->postJson("/api/v1/livraisons/factures/{$factureId}/commissions/paiement")
            ->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────
    //  6. Paiement commission OK si facture payée
    // ─────────────────────────────────────────────────────
    public function test_paiement_commission_ok_si_facture_payee(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $vehiculeId = $this->withHeaders($header)
            ->postJson('/api/v1/livraisons/one-shot', $this->oneShotPayload())
            ->json('data.vehicule.id');

        // Facture : 100 packs × 1000/pack = 100 000 commission brute
        $factureId = $this->withHeaders($header)
            ->postJson('/api/v1/livraisons/factures', [
                'vehicule_id'  => $vehiculeId,
                'packs_charges' => 100,
                'montant_brut'  => 50000,
            ])->json('data.id');

        // Paiement total de la facture
        $this->withHeaders($header)->postJson('/api/v1/encaissements-livraisons', [
            'facture_livraison_id' => $factureId,
            'montant'              => 50000,
            'date_encaissement'    => now()->toDateString(),
            'mode_paiement'        => 'virement',
        ])->assertCreated();

        // Paiement commission → 201
        $response = $this->withHeaders($header)
            ->postJson("/api/v1/livraisons/factures/{$factureId}/commissions/paiement");

        $response->assertCreated()
            ->assertJsonPath('data.statut', 'paye');

        // 100 packs × 1000 = 100 000 brut ; proprio 60% = 60 000, livreur 40% = 40 000
        $this->assertEquals('100000.00', $response->json('data.commission_brute_totale'));
        $this->assertEquals('60000.00',  $response->json('data.part_proprietaire_nette'));
        $this->assertEquals('40000.00',  $response->json('data.part_livreur_nette'));
    }

    // ─────────────────────────────────────────────────────
    //  7. Double paiement commission → 409
    // ─────────────────────────────────────────────────────
    public function test_double_paiement_commission_retourne_409(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $vehiculeId = $this->withHeaders($header)
            ->postJson('/api/v1/livraisons/one-shot', $this->oneShotPayload())
            ->json('data.vehicule.id');

        $factureId = $this->withHeaders($header)
            ->postJson('/api/v1/livraisons/factures', [
                'vehicule_id'  => $vehiculeId,
                'packs_charges' => 50,
                'montant_brut'  => 25000,
            ])->json('data.id');

        $this->withHeaders($header)->postJson('/api/v1/encaissements-livraisons', [
            'facture_livraison_id' => $factureId,
            'montant'              => 25000,
            'date_encaissement'    => now()->toDateString(),
            'mode_paiement'        => 'especes',
        ])->assertCreated();

        $this->withHeaders($header)->postJson("/api/v1/livraisons/factures/{$factureId}/commissions/paiement")->assertCreated();
        $this->withHeaders($header)->postJson("/api/v1/livraisons/factures/{$factureId}/commissions/paiement")->assertStatus(409);
    }

    // ─────────────────────────────────────────────────────
    //  8. Déductions réduisent les parts nettes
    // ─────────────────────────────────────────────────────
    public function test_deductions_reduisent_les_parts_nettes(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $vehiculeId = $this->withHeaders($header)
            ->postJson('/api/v1/livraisons/one-shot', $this->oneShotPayload())
            ->json('data.vehicule.id');

        $factureId = $this->withHeaders($header)
            ->postJson('/api/v1/livraisons/factures', [
                'vehicule_id'  => $vehiculeId,
                'packs_charges' => 100,
                'montant_brut'  => 50000,
            ])->json('data.id');

        // Déduction carburant sur propriétaire : 5000
        $this->withHeaders($header)->postJson("/api/v1/livraisons/factures/{$factureId}/deductions", [
            'cible'          => 'proprietaire',
            'type_deduction' => 'carburant',
            'montant'        => 5000,
        ])->assertCreated();

        // Calcul : 100 × 1000 = 100 000 brut
        // proprio brut 60 000 → net 55 000 (- 5000 carburant)
        // livreur brut 40 000 → net 40 000 (aucune déduction)
        $response = $this->withHeaders($header)
            ->getJson("/api/v1/livraisons/factures/{$factureId}/commissions/calcul");

        $response->assertOk()
            ->assertJson(['data' => ['deductions_proprietaire' => 5000]])
            ->assertJson(['data' => ['deductions_livreur'      => 0]])
            ->assertJson(['data' => ['part_proprietaire_nette' => 55000]])
            ->assertJson(['data' => ['part_livreur_nette'      => 40000]]);
    }

    // ─────────────────────────────────────────────────────
    //  9. One-shot sans photo → 422
    // ─────────────────────────────────────────────────────
    public function test_one_shot_sans_photo_ok(): void
    {
        Sanctum::actingAs($this->staff);

        $payload = $this->oneShotPayload();
        unset($payload['photo']);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->postJson('/api/v1/livraisons/one-shot', $payload)
            ->assertCreated();

        $response->assertJsonPath('data.vehicule.photo_path', null)
            ->assertJsonPath('data.vehicule.photo_url', null);
    }
}
