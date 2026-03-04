<?php

namespace Tests\Feature\Organisation;

use App\Enums\UserType;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autorisations Organisation — seul super_admin peut gérer les organisations.
 *
 * Couverture :
 *  - Utilisateur non authentifié → 401
 *  - admin_entreprise → 403 sur toutes les routes organisations
 *  - manager / comptable / employe / commerciale → 403
 *  - super_admin cross-org via header X-Organisation-Id
 *  - Non-super_admin ne peut pas accéder à une autre org via header
 *  - Non-régression : endpoints usine existants toujours accessibles
 */
class OrganisationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $adminEntreprise;
    private Site $usine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->usine = Site::factory()->create(['code' => 'AUTH-TEST', 'nom' => 'Site Auth Test']);

        $this->superAdmin = User::factory()->create(['type' => UserType::STAFF->value]);
        $this->superAdmin->assignRole('super_admin');

        $this->adminEntreprise = User::factory()->create(['type' => UserType::STAFF->value]);
        $this->adminEntreprise->assignRole('admin_entreprise');
        $this->adminEntreprise->sites()->attach($this->usine->id, ['role' => 'owner_siege', 'is_default' => true]);
        $this->adminEntreprise->update(['default_site_id' => $this->usine->id]);
    }

    // ── ACCÈS NON AUTHENTIFIÉ ─────────────────────────────────────────────

    public function test_unauthenticated_user_gets_401_on_organisations(): void
    {
        $this->getJson('/api/v1/organisations')->assertUnauthorized();
    }

    // ── ADMIN ENTREPRISE BLOQUÉ ───────────────────────────────────────────

    public function test_admin_entreprise_cannot_list_organisations(): void
    {
        $this->actingAs($this->adminEntreprise)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->getJson('/api/v1/organisations')
            ->assertForbidden();
    }

    public function test_admin_entreprise_cannot_create_organisation(): void
    {
        $this->actingAs($this->adminEntreprise)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->postJson('/api/v1/organisations', ['nom' => 'Test', 'code' => 'TST-001'])
            ->assertForbidden();
    }

    public function test_admin_entreprise_cannot_update_organisation(): void
    {
        $org = Organisation::factory()->create();

        $this->actingAs($this->adminEntreprise)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->putJson("/api/v1/organisations/{$org->id}", ['nom' => 'Hack'])
            ->assertForbidden();
    }

    public function test_admin_entreprise_cannot_delete_organisation(): void
    {
        $org = Organisation::factory()->create();

        $this->actingAs($this->adminEntreprise)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->deleteJson("/api/v1/organisations/{$org->id}")
            ->assertForbidden();
    }

    // ── AUTRES RÔLES BLOQUÉS ──────────────────────────────────────────────

    /** @dataProvider nonSuperAdminRoles */
    public function test_non_super_admin_roles_cannot_list_organisations(string $role): void
    {
        $user = User::factory()->create(['type' => UserType::STAFF->value]);
        $user->assignRole($role);
        $user->sites()->attach($this->usine->id, ['role' => 'staff', 'is_default' => true]);
        $user->update(['default_site_id' => $this->usine->id]);

        $this->actingAs($user)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->getJson('/api/v1/organisations')
            ->assertForbidden();
    }

    public static function nonSuperAdminRoles(): array
    {
        return [
            'manager'    => ['manager'],
            'comptable'  => ['comptable'],
            'employe'    => ['employe'],
            'commerciale' => ['commerciale'],
        ];
    }

    // ── SUPER_ADMIN CROSS-ORG ─────────────────────────────────────────────

    public function test_super_admin_can_access_any_organisation_via_header(): void
    {
        $org = Organisation::factory()->create();

        // super_admin n'appartient à aucune org, mais le header X-Organisation-Id
        // lui donne accès explicite.
        $response = $this->actingAs($this->superAdmin)
            ->withHeaders(['X-Organisation-Id' => $org->id])
            ->getJson('/api/v1/organisations');

        $response->assertOk();
    }

    public function test_non_super_admin_cannot_access_another_organisation_via_header(): void
    {
        $orgAutre = Organisation::factory()->create(['code' => 'ORG-AUTRE']);

        // admin_entreprise appartient à une org différente (ou null) — il ne peut
        // pas se substituer à une autre org via le header
        $this->actingAs($this->adminEntreprise)
            ->withHeaders([
                'X-Site-Id'          => $this->usine->id,
                'X-Organisation-Id'   => $orgAutre->id,
            ])
            ->getJson('/api/v1/organisations')
            ->assertForbidden();
    }

    // ── NON-RÉGRESSION ENDPOINTS USINE ────────────────────────────────────

    public function test_existing_usine_endpoint_still_works_after_organisation_introduction(): void
    {
        $response = $this->actingAs($this->adminEntreprise)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->getJson('/api/v1/sites');

        // doit retourner 200, pas 403 — les routes sites sont inchangées
        $response->assertOk();
    }

    public function test_existing_roles_endpoint_still_works(): void
    {
        $response = $this->actingAs($this->adminEntreprise)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->getJson('/api/v1/roles');

        $response->assertOk();
    }
}
