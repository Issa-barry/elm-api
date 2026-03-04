<?php

namespace Tests\Feature\Role;

use App\Enums\UserType;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Non-régression : migration admin → admin_entreprise
 *
 * Vérifie que :
 *  1. Le rôle admin_entreprise existe et admin n'existe plus
 *  2. admin_entreprise a toutes les permissions sauf users.create et users.delete
 *  3. admin_entreprise peut lire/mettre à jour les utilisateurs (block/unblock)
 *  4. admin_entreprise NE PEUT PAS créer, supprimer ni archiver des utilisateurs
 *  5. admin_entreprise accède aux routes de gestion des rôles
 *  6. Un utilisateur anciennement « admin » (migré vers admin_entreprise) conserve ses accès
 */
class AdminEntrepriseRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private Site $usine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->usine = Site::factory()->create(['code' => 'ADM-TEST', 'nom' => 'Site Test Admin']);

        $this->adminUser = User::factory()->create([
            'type' => UserType::STAFF->value,
        ]);
        $this->adminUser->assignRole('admin_entreprise');
        $this->adminUser->sites()->attach($this->usine->id, ['role' => 'owner_siege', 'is_default' => true]);
        $this->adminUser->update(['default_site_id' => $this->usine->id]);
    }

    // ──────────────────────────────────────────────────────────────
    // 1. Structure du rôle
    // ──────────────────────────────────────────────────────────────

    public function test_role_admin_entreprise_exists(): void
    {
        $this->assertDatabaseHas('roles', ['name' => 'admin_entreprise']);
    }

    public function test_role_admin_does_not_exist(): void
    {
        $this->assertDatabaseMissing('roles', ['name' => 'admin']);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. Permissions attendues (tout sauf users.create + users.delete)
    // ──────────────────────────────────────────────────────────────

    public function test_admin_entreprise_has_users_read_permission(): void
    {
        $this->assertTrue($this->adminUser->hasPermissionTo('users.read'));
    }

    public function test_admin_entreprise_has_users_update_permission(): void
    {
        $this->assertTrue($this->adminUser->hasPermissionTo('users.update'));
    }

    public function test_admin_entreprise_cannot_create_users(): void
    {
        $this->assertFalse($this->adminUser->hasPermissionTo('users.create'));
    }

    public function test_admin_entreprise_cannot_delete_users(): void
    {
        $this->assertFalse($this->adminUser->hasPermissionTo('users.delete'));
    }

    public function test_admin_entreprise_has_all_other_permissions(): void
    {
        // organisations.* est réservé au super_admin — exclu du périmètre admin_entreprise
        $allPermissions = Permission::whereNotIn('name', [
            'users.create',
            'users.delete',
            'organisations.create',
            'organisations.read',
            'organisations.update',
            'organisations.delete',
        ])->pluck('name');

        foreach ($allPermissions as $permission) {
            $this->assertTrue(
                $this->adminUser->hasPermissionTo($permission),
                "admin_entreprise devrait avoir la permission [{$permission}]"
            );
        }
    }

    public function test_admin_entreprise_cannot_manage_organisations(): void
    {
        foreach (['organisations.create', 'organisations.read', 'organisations.update', 'organisations.delete'] as $perm) {
            $this->assertFalse(
                $this->adminUser->hasPermissionTo($perm),
                "admin_entreprise ne devrait PAS avoir la permission [{$perm}]"
            );
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 3. Accès HTTP — lecture utilisateurs (autorisé)
    // ──────────────────────────────────────────────────────────────

    public function test_admin_entreprise_can_list_users(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->getJson('/api/v1/users');

        $this->assertNotEquals(403, $response->status());
    }

    // ──────────────────────────────────────────────────────────────
    // 4. Accès HTTP — création utilisateur (INTERDIT)
    // ──────────────────────────────────────────────────────────────

    public function test_admin_entreprise_cannot_create_user_via_api(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->postJson('/api/v1/users', [
                'nom'             => 'Test',
                'prenom'          => 'User',
                'phone'           => '+224620000099',
                'type'            => 'staff',
                'role'            => 'employe',
                'pays'            => 'Guinée',
                'code_pays'       => 'GN',
                'code_phone_pays' => '+224',
                'ville'           => 'Conakry',
                'quartier'        => 'Kaloum',
                'password'        => 'secret1234',
            ]);

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────
    // 5. Accès HTTP — suppression utilisateur (INTERDIT)
    // ──────────────────────────────────────────────────────────────

    public function test_admin_entreprise_cannot_delete_user_via_api(): void
    {
        $cible = User::factory()->create(['type' => UserType::STAFF->value]);

        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->deleteJson("/api/v1/users/{$cible->id}");

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────
    // 6. Accès HTTP — archivage utilisateur (INTERDIT)
    // ──────────────────────────────────────────────────────────────

    public function test_admin_entreprise_cannot_archive_user_via_api(): void
    {
        $cible = User::factory()->create(['type' => UserType::STAFF->value]);

        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->patchJson("/api/v1/users/{$cible->id}/archiver");

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────
    // 7. Accès HTTP — toggle-status utilisateur (AUTORISÉ = block/unblock)
    // ──────────────────────────────────────────────────────────────

    public function test_admin_entreprise_can_toggle_user_status(): void
    {
        $cible = User::factory()->create(['type' => UserType::STAFF->value]);
        $cible->sites()->attach($this->usine->id, ['role' => 'staff', 'is_default' => true]);

        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->patchJson("/api/v1/users/{$cible->id}/toggle-status");

        $this->assertNotEquals(403, $response->status());
    }

    // ──────────────────────────────────────────────────────────────
    // 8. Accès routes rôles (admin_entreprise middleware)
    // ──────────────────────────────────────────────────────────────

    public function test_admin_entreprise_can_list_roles(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->getJson('/api/v1/roles');

        $this->assertNotEquals(403, $response->status());
    }

    // ──────────────────────────────────────────────────────────────
    // 9. Non-régression : rôle admin_entreprise protégé contre suppression/modification
    // ──────────────────────────────────────────────────────────────

    public function test_admin_entreprise_role_cannot_be_deleted(): void
    {
        $role = Role::where('name', 'admin_entreprise')->first();

        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertStatus(403);
    }

    public function test_admin_entreprise_role_cannot_be_renamed(): void
    {
        $role = Role::where('name', 'admin_entreprise')->first();

        $response = $this->actingAs($this->adminUser)
            ->withHeaders(['X-Site-Id' => $this->usine->id])
            ->putJson("/api/v1/roles/{$role->id}", ['name' => 'super_admin']);

        $response->assertStatus(403);
    }
}
