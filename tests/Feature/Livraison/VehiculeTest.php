<?php

namespace Tests\Feature\Livraison;

use App\Enums\UsineType;
use App\Models\Proprietaire;
use App\Models\Usine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VehiculeTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private Usine $usine;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        Permission::findOrCreate('vehicules.create', 'web');
        Permission::findOrCreate('vehicules.read', 'web');
        Permission::findOrCreate('vehicules.update', 'web');
        Permission::findOrCreate('vehicules.delete', 'web');

        $this->usine = Usine::create([
            'nom'    => 'Usine Vehicule Test',
            'code'   => 'VEH-TEST',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        $this->staff = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usine->id,
        ]);
        $this->staff->usines()->attach($this->usine->id, ['role' => 'manager', 'is_default' => true]);
        $this->staff->givePermissionTo(['vehicules.create', 'vehicules.read', 'vehicules.update', 'vehicules.delete']);
    }

    private function payload(array $overrides = []): array
    {
        $proprietaire = Proprietaire::factory()->create();

        return array_merge([
            'nom_vehicule'             => 'Camion Alpha',
            'immatriculation'          => 'GN-1234-A',
            'type_vehicule'            => 'camion',
            'capacite_packs'           => 200,
            'proprietaire_id'          => $proprietaire->id,
            'pris_en_charge_par_usine' => false,
            'mode_commission'          => 'forfait',
            'valeur_commission'        => 500,
            'pourcentage_proprietaire' => 60,
            'pourcentage_livreur'      => 40,
            'photo'                    => UploadedFile::fake()->image('vehicule.jpg', 800, 600),
        ], $overrides);
    }

    public function test_creation_vehicule_avec_photo_ok(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->postJson('/api/v1/vehicules', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.nom_vehicule', 'Camion Alpha')
            ->assertJsonPath('data.immatriculation', 'GN-1234-A');

        $photoPath = $response->json('data.photo_path');
        Storage::disk('public')->assertExists($photoPath);

        $this->assertStringContainsString('/storage/', $response->json('data.photo_url'));
    }

    public function test_creation_vehicule_sans_photo_ok(): void
    {
        Sanctum::actingAs($this->staff);

        $payload = $this->payload();
        unset($payload['photo']);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->postJson('/api/v1/vehicules', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.photo_path', null)
            ->assertJsonPath('data.photo_url', null);
    }

    public function test_creation_vehicule_sans_capacite_utilise_default_selon_type(): void
    {
        Sanctum::actingAs($this->staff);

        $payload = $this->payload([
            'type_vehicule' => 'vanne',
        ]);
        unset($payload['capacite_packs']);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->postJson('/api/v1/vehicules', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.type_vehicule', 'vanne')
            ->assertJsonPath('data.capacite_packs', 150);
    }

    public function test_update_type_vehicule_applique_capacite_par_defaut_si_absente(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $createResponse = $this->withHeaders($header)->postJson('/api/v1/vehicules', $this->payload());
        $createResponse->assertCreated();

        $id = $createResponse->json('data.id');

        $updateResponse = $this->withHeaders($header)->postJson("/api/v1/vehicules/{$id}", [
            'type_vehicule' => 'tricyle',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.type_vehicule', 'tricycle')
            ->assertJsonPath('data.capacite_packs', 70);
    }

    public function test_pourcentages_invalides_retourne_422(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usine->id)
            ->postJson('/api/v1/vehicules', $this->payload([
                'pourcentage_proprietaire' => 50,
                'pourcentage_livreur'      => 30,
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pourcentage_livreur']);
    }

    public function test_immatriculation_unique_par_usine(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $this->withHeaders($header)->postJson('/api/v1/vehicules', $this->payload(['immatriculation' => 'GN-0001-X']))->assertCreated();
        $this->withHeaders($header)->postJson('/api/v1/vehicules', $this->payload(['immatriculation' => 'GN-0001-X']))->assertUnprocessable();
    }

    public function test_remplacement_photo_vehicule(): void
    {
        Sanctum::actingAs($this->staff);

        $header = ['X-Usine-Id' => (string) $this->usine->id];

        $createResponse = $this->withHeaders($header)->postJson('/api/v1/vehicules', $this->payload());
        $createResponse->assertCreated();

        $id           = $createResponse->json('data.id');
        $oldPhotoPath = $createResponse->json('data.photo_path');

        Storage::disk('public')->assertExists($oldPhotoPath);

        $newPhoto = UploadedFile::fake()->image('nouveau.jpg', 800, 600);
        $this->withHeaders($header)->postJson("/api/v1/vehicules/{$id}", [
            'photo' => $newPhoto,
        ])->assertOk();

        Storage::disk('public')->assertMissing($oldPhotoPath);
    }

    public function test_non_authentifie_retourne_401(): void
    {
        $response = $this->postJson('/api/v1/vehicules', $this->payload());
        $response->assertUnauthorized();
    }
}
