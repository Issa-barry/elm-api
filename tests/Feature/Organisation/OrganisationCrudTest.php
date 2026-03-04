<?php

namespace Tests\Feature\Organisation;

use App\Enums\OrganisationStatut;
use App\Enums\UserType;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD Organisation — accès réservé super_admin.
 *
 * Couverture :
 *  - Index : liste des organisations
 *  - Store : création avec validation
 *  - Show  : détail avec usines_count
 *  - Update : mise à jour partielle
 *  - Destroy : soft delete + protection si usines actives
 */
class OrganisationCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        // Créer un super_admin sans usine (cross-org)
        $this->superAdmin = User::factory()->create([
            'type' => UserType::STAFF->value,
        ]);
        $this->superAdmin->assignRole('super_admin');
    }

    private function actingAsSuperAdmin(): static
    {
        return $this->actingAs($this->superAdmin);
    }

    // ── INDEX ────────────────────────────────────────────────────────────

    public function test_super_admin_can_list_organisations(): void
    {
        // Le backfill migration peut avoir créé ELM-GN si des usines existent déjà.
        // On prend le compte de référence avant création pour être résilient.
        $baseline = Organisation::count();
        Organisation::factory()->count(3)->create();

        $response = $this->actingAsSuperAdmin()
            ->getJson('/api/v1/organisations');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount($baseline + 3, 'data');
    }

    public function test_index_returns_sites_count(): void
    {
        $org = Organisation::factory()->create();
        Site::factory()->count(2)->create(['organisation_id' => $org->id]);

        $response = $this->actingAsSuperAdmin()
            ->getJson('/api/v1/organisations');

        $response->assertOk();
        $data = collect($response->json('data'))->firstWhere('id', $org->id);
        $this->assertEquals(2, $data['sites_count']);
    }

    // ── STORE ─────────────────────────────────────────────────────────────

    public function test_super_admin_can_create_organisation(): void
    {
        $payload = [
            'nom'   => 'Acme BTP',
            'code'  => 'ACME-BTP',
            'email' => 'contact@acme.gn',
            'pays'  => 'Guinee',
            'ville' => 'Conakry',
        ];

        $response = $this->actingAsSuperAdmin()
            ->postJson('/api/v1/organisations', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'ACME-BTP')
            ->assertJsonPath('data.statut', OrganisationStatut::ACTIVE->value);

        $this->assertDatabaseHas('organisations', ['code' => 'ACME-BTP']);
    }

    public function test_store_requires_nom_and_code(): void
    {
        $response = $this->actingAsSuperAdmin()
            ->postJson('/api/v1/organisations', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['nom', 'code']);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        Organisation::factory()->create(['code' => 'DUP-001']);

        $response = $this->actingAsSuperAdmin()
            ->postJson('/api/v1/organisations', [
                'nom'  => 'Test',
                'code' => 'DUP-001',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    // ── SHOW ──────────────────────────────────────────────────────────────

    public function test_super_admin_can_show_organisation(): void
    {
        $org = Organisation::factory()->create(['nom' => 'ELM Test']);

        $response = $this->actingAsSuperAdmin()
            ->getJson("/api/v1/organisations/{$org->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $org->id)
            ->assertJsonPath('data.nom', 'ELM Test');
    }

    public function test_show_returns_404_for_nonexistent_organisation(): void
    {
        $response = $this->actingAsSuperAdmin()
            ->getJson('/api/v1/organisations/99999');

        $response->assertNotFound();
    }

    // ── UPDATE ────────────────────────────────────────────────────────────

    public function test_super_admin_can_update_organisation(): void
    {
        $org = Organisation::factory()->create(['nom' => 'Ancien Nom', 'statut' => OrganisationStatut::ACTIVE]);

        $response = $this->actingAsSuperAdmin()
            ->putJson("/api/v1/organisations/{$org->id}", [
                'nom'    => 'Nouveau Nom',
                'statut' => 'suspended',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.nom', 'Nouveau Nom')
            ->assertJsonPath('data.statut', 'suspended');

        $this->assertDatabaseHas('organisations', ['id' => $org->id, 'nom' => 'Nouveau Nom']);
    }

    public function test_update_rejects_duplicate_code_on_another_organisation(): void
    {
        $org1 = Organisation::factory()->create(['code' => 'ORG-001']);
        $org2 = Organisation::factory()->create(['code' => 'ORG-002']);

        $response = $this->actingAsSuperAdmin()
            ->putJson("/api/v1/organisations/{$org2->id}", ['code' => 'ORG-001']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_update_allows_same_code_on_same_organisation(): void
    {
        $org = Organisation::factory()->create(['code' => 'ORG-SAME', 'nom' => 'Nom initial']);

        $response = $this->actingAsSuperAdmin()
            ->putJson("/api/v1/organisations/{$org->id}", [
                'code' => 'ORG-SAME',
                'nom'  => 'Nom mis à jour',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.nom', 'Nom mis à jour');
    }

    // ── DESTROY ───────────────────────────────────────────────────────────

    public function test_super_admin_can_soft_delete_organisation_without_usines(): void
    {
        $org = Organisation::factory()->create();

        $response = $this->actingAsSuperAdmin()
            ->deleteJson("/api/v1/organisations/{$org->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('organisations', ['id' => $org->id]);
    }

    public function test_destroy_blocked_if_active_sites_exist(): void
    {
        $org   = Organisation::factory()->create();
        Site::factory()->create(['organisation_id' => $org->id]);

        $response = $this->actingAsSuperAdmin()
            ->deleteJson("/api/v1/organisations/{$org->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('organisations', ['id' => $org->id, 'deleted_at' => null]);
    }
}
