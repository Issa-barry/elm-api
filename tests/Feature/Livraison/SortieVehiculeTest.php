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

class SortieVehiculeTest extends TestCase
{
    use RefreshDatabase;

    private User     $staff;
    private Usine    $usine;
    private Vehicule $vehicule;
    private Livreur  $livreur;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach (['sorties.create', 'sorties.read', 'sorties.update', 'vehicules.create'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $this->usine = Usine::create([
            'nom'    => 'Usine Sortie Test',
            'code'   => 'SRT-TEST',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        $this->staff = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usine->id,
        ]);
        $this->staff->usines()->attach($this->usine->id, ['role' => 'manager', 'is_default' => true]);
        $this->staff->givePermissionTo(['sorties.create', 'sorties.read', 'sorties.update', 'vehicules.create']);

        $this->livreur  = Livreur::factory()->create();
        $proprietaire   = Proprietaire::factory()->create();

        $this->vehicule = Vehicule::withoutGlobalScopes()->create([
            'usine_id'                  => $this->usine->id,
            'nom_vehicule'              => 'Camion Test',
            'immatriculation'           => 'SRT-001',
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
    }

    // ─────────────────────────────────────────────────────
    //  Création départ OK + snapshots
    // ─────────────────────────────────────────────────────
    public function test_creation_sortie_avec_snapshots(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->postJson('/api/v1/sorties-vehicules', [
                'vehicule_id'         => $this->vehicule->id,
                'livreur_id_effectif' => $this->livreur->id,
                'packs_charges'       => 80,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.statut_sortie', 'en_cours')
            ->assertJsonPath('data.snapshot_mode_commission', 'forfait')
            ->assertJsonPath('data.packs_charges', 80);

        $this->assertEquals(300, $response->json('data.snapshot_valeur_commission'));
        $this->assertEquals(60, $response->json('data.snapshot_pourcentage_proprietaire'));
        $this->assertEquals(40, $response->json('data.snapshot_pourcentage_livreur'));
    }

    // ─────────────────────────────────────────────────────
    //  Règle : packs_charges > capacite_packs → erreur
    // ─────────────────────────────────────────────────────
    public function test_packs_charges_depasse_capacite_retourne_erreur(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->postJson('/api/v1/sorties-vehicules', [
                'vehicule_id'         => $this->vehicule->id,
                'livreur_id_effectif' => $this->livreur->id,
                'packs_charges'       => 200, // > capacite 100
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['packs_charges']);
    }

    // ─────────────────────────────────────────────────────
    //  Un seul départ en cours par véhicule
    // ─────────────────────────────────────────────────────
    public function test_un_seul_depart_en_cours_par_vehicule(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $this->withHeaders($header)->postJson('/api/v1/sorties-vehicules', [
            'vehicule_id' => $this->vehicule->id, 'livreur_id_effectif' => $this->livreur->id, 'packs_charges' => 50,
        ])->assertCreated();

        $response = $this->withHeaders($header)->postJson('/api/v1/sorties-vehicules', [
            'vehicule_id' => $this->vehicule->id, 'livreur_id_effectif' => $this->livreur->id, 'packs_charges' => 30,
        ]);

        $response->assertStatus(409);
    }

    // ─────────────────────────────────────────────────────
    //  Retour OK
    // ─────────────────────────────────────────────────────
    public function test_retour_sortie_ok(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $id = $this->withHeaders($header)->postJson('/api/v1/sorties-vehicules', [
            'vehicule_id' => $this->vehicule->id, 'livreur_id_effectif' => $this->livreur->id, 'packs_charges' => 100,
        ])->json('data.id');

        $response = $this->withHeaders($header)->patchJson("/api/v1/sorties-vehicules/{$id}/retour", [
            'packs_retour' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.statut_sortie', 'retourne')
            ->assertJsonPath('data.packs_retour', 10)
            ->assertJsonPath('data.packs_livres', 90);
    }

    // ─────────────────────────────────────────────────────
    //  Clôture sortie → statut cloture
    // ─────────────────────────────────────────────────────
    public function test_cloture_sortie_ok(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $id = $this->withHeaders($header)->postJson('/api/v1/sorties-vehicules', [
            'vehicule_id' => $this->vehicule->id, 'livreur_id_effectif' => $this->livreur->id, 'packs_charges' => 50,
        ])->json('data.id');

        $this->withHeaders($header)->patchJson("/api/v1/sorties-vehicules/{$id}/retour", ['packs_retour' => 5])->assertOk();
        $this->withHeaders($header)->patchJson("/api/v1/sorties-vehicules/{$id}/cloture")->assertOk()
            ->assertJsonPath('data.statut_sortie', 'cloture');
    }
}
