<?php

namespace Tests\Feature\Site;

use App\Enums\SiteStatut;
use App\Enums\SiteType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SiteCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $siege;
    private User $manager;
    private Site $siteSiege;
    private Site $siteA;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        foreach (['sites.create', 'sites.read', 'sites.update', 'sites.delete'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        // Site siège
        $this->siteSiege = Site::create([
            'nom'    => 'ELM Siège',
            'code'   => 'CRUD-SIEGE',
            'type'   => SiteType::SIEGE->value,
            'statut' => SiteStatut::ACTIVE->value,
        ]);

        // Site normale
        $this->siteA = Site::create([
            'nom'    => 'Site Alpha',
            'code'   => 'CRUD-A',
            'type'   => SiteType::USINE->value,
            'statut' => SiteStatut::ACTIVE->value,
            'pays'   => 'Guinée',
            'ville'  => 'Conakry',
        ]);

        // User siège (owner_siege + super_admin → tous les droits)
        Role::firstOrCreate(['name' => 'super_admin']);
        $this->siege = User::factory()->create(['type' => 'staff', 'default_site_id' => $this->siteSiege->id]);
        $this->siege->sites()->attach($this->siteSiege->id, ['role' => 'owner_siege', 'is_default' => true]);
        $this->siege->assignRole('super_admin');

        // User manager de siteA seulement
        $this->manager = User::factory()->create(['type' => 'staff', 'default_site_id' => $this->siteA->id]);
        $this->manager->sites()->attach($this->siteA->id, ['role' => 'manager', 'is_default' => true]);
        $this->manager->givePermissionTo(['sites.read']);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  INDEX
    // ═══════════════════════════════════════════════════════════════════

    public function test_index_siege_voit_tous_les_sites(): void
    {
        Sanctum::actingAs($this->siege);

        $response = $this->getJson('/api/v1/sites');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_index_manager_voit_seulement_ses_sites(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/sites');

        $response->assertOk();
        $codes = array_column($response->json('data'), 'code');
        $this->assertContains('CRUD-A', $codes);
        $this->assertNotContains('CRUD-SIEGE', $codes);
    }

    public function test_index_non_authentifie_retourne_401(): void
    {
        $this->getJson('/api/v1/sites')->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  SHOW
    // ═══════════════════════════════════════════════════════════════════

    public function test_show_siege_peut_voir_nimporte_quel_site(): void
    {
        Sanctum::actingAs($this->siege);

        $response = $this->getJson("/api/v1/sites/{$this->siteA->id}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'CRUD-A')
            ->assertJsonPath('data.pays', 'Guinée')
            ->assertJsonPath('data.ville', 'Conakry');
    }

    public function test_show_manager_peut_voir_son_site(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/sites/{$this->siteA->id}");

        $response->assertOk()->assertJsonPath('data.code', 'CRUD-A');
    }

    public function test_show_manager_ne_peut_pas_voir_site_etranger(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/sites/{$this->siteSiege->id}");

        $response->assertForbidden();
    }

    public function test_show_retourne_404_si_inexistant(): void
    {
        Sanctum::actingAs($this->siege);

        $this->getJson('/api/v1/sites/99999')->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STORE
    // ═══════════════════════════════════════════════════════════════════

    public function test_store_siege_cree_un_site_avec_localisation(): void
    {
        Sanctum::actingAs($this->siege);

        $response = $this->postJson('/api/v1/sites', [
            'nom'       => 'Nouveau Site',
            'code'      => 'CRUD-NEW',
            'type'      => SiteType::USINE->value,
            'pays'      => 'Guinée',
            'ville'     => 'Kindia',
            'quartier'  => 'Centre-ville',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'CRUD-NEW')
            ->assertJsonPath('data.pays', 'Guinée')
            ->assertJsonPath('data.ville', 'Kindia')
            ->assertJsonPath('data.quartier', 'Centre-ville')
            ->assertJsonPath('data.statut', SiteStatut::ACTIVE->value);

        $this->assertDatabaseHas('sites', ['code' => 'CRUD-NEW', 'pays' => 'Guinée']);
    }

    public function test_store_non_siege_est_interdit(): void
    {
        Sanctum::actingAs($this->manager);

        $this->manager->givePermissionTo('sites.create');

        $response = $this->postJson('/api/v1/sites', [
            'nom'  => 'Tentative',
            'code' => 'CRUD-FAIL',
            'type' => SiteType::USINE->value,
        ]);

        $response->assertForbidden();
    }

    public function test_store_code_doit_etre_unique(): void
    {
        Sanctum::actingAs($this->siege);

        $this->postJson('/api/v1/sites', [
            'nom'  => 'Doublon',
            'code' => 'CRUD-A', // déjà existant
            'type' => SiteType::USINE->value,
        ])->assertUnprocessable();
    }

    public function test_store_code_doit_respecter_le_format(): void
    {
        Sanctum::actingAs($this->siege);

        $this->postJson('/api/v1/sites', [
            'nom'  => 'Mauvais code',
            'code' => 'crud a!',  // minuscules + espace + spécial
            'type' => SiteType::USINE->value,
        ])->assertUnprocessable();
    }

    public function test_store_avec_parent_id(): void
    {
        Sanctum::actingAs($this->siege);

        $response = $this->postJson('/api/v1/sites', [
            'nom'       => 'Sous-site',
            'code'      => 'CRUD-SUB',
            'type'      => SiteType::USINE->value,
            'parent_id' => $this->siteSiege->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.parent_id', $this->siteSiege->id);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PATCH (UPDATE)
    // ═══════════════════════════════════════════════════════════════════

    public function test_patch_siege_met_a_jour_les_champs(): void
    {
        Sanctum::actingAs($this->siege);

        $response = $this->patchJson("/api/v1/sites/{$this->siteA->id}", [
            'pays'     => 'Sénégal',
            'ville'    => 'Dakar',
            'quartier' => 'Plateau',
            'statut'   => SiteStatut::INACTIVE->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.pays', 'Sénégal')
            ->assertJsonPath('data.ville', 'Dakar')
            ->assertJsonPath('data.quartier', 'Plateau')
            ->assertJsonPath('data.statut', SiteStatut::INACTIVE->value);
    }

    public function test_patch_non_siege_est_interdit(): void
    {
        Sanctum::actingAs($this->manager);

        $this->manager->givePermissionTo('sites.update');

        $this->patchJson("/api/v1/sites/{$this->siteA->id}", [
            'nom' => 'Tentative update',
        ])->assertForbidden();
    }

    public function test_patch_code_unique_ignore_propre_site(): void
    {
        Sanctum::actingAs($this->siege);

        // Même code = OK (ignorer soi-même)
        $this->patchJson("/api/v1/sites/{$this->siteA->id}", [
            'code' => 'CRUD-A',
        ])->assertOk();

        // Code d'un autre site = 422
        $this->patchJson("/api/v1/sites/{$this->siteA->id}", [
            'code' => 'CRUD-SIEGE',
        ])->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  DESTROY
    // ═══════════════════════════════════════════════════════════════════

    public function test_destroy_siege_supprime_un_site(): void
    {
        Sanctum::actingAs($this->siege);

        $siteTemp = Site::create([
            'nom'  => 'Temp',
            'code' => 'CRUD-TMP',
            'type' => SiteType::USINE->value,
        ]);

        $this->deleteJson("/api/v1/sites/{$siteTemp->id}")
            ->assertOk();

        $this->assertSoftDeleted('sites', ['id' => $siteTemp->id]);
    }

    public function test_destroy_siege_ne_peut_pas_supprimer_site_siege(): void
    {
        Sanctum::actingAs($this->siege);

        $this->deleteJson("/api/v1/sites/{$this->siteSiege->id}")
            ->assertForbidden();
    }

    public function test_destroy_non_siege_est_interdit(): void
    {
        Sanctum::actingAs($this->manager);

        $this->manager->givePermissionTo('sites.delete');

        $this->deleteJson("/api/v1/sites/{$this->siteA->id}")
            ->assertForbidden();
    }

    public function test_destroy_retourne_404_si_inexistant(): void
    {
        Sanctum::actingAs($this->siege);

        $this->deleteJson('/api/v1/sites/99999')->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  USERS D'UN SITE
    // ═══════════════════════════════════════════════════════════════════

    public function test_users_siege_voit_les_users_dun_site(): void
    {
        Sanctum::actingAs($this->siege);

        $response = $this->getJson("/api/v1/sites/{$this->siteA->id}/users");

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($this->manager->id, $ids);
    }

    public function test_users_manager_voit_les_users_de_son_site(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/sites/{$this->siteA->id}/users");

        $response->assertOk();
        // Chaque user doit avoir role_site et is_default
        $user = collect($response->json('data'))->firstWhere('id', $this->manager->id);
        $this->assertNotNull($user);
        $this->assertEquals('manager', $user['role_site']);
        $this->assertTrue($user['is_default']);
    }

    public function test_users_manager_ne_peut_pas_voir_site_etranger(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/sites/{$this->siteSiege->id}/users")
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  AFFECTATION USER ↔ SITE
    // ═══════════════════════════════════════════════════════════════════

    public function test_siege_peut_affecter_un_user_a_un_site(): void
    {
        Sanctum::actingAs($this->siege);

        $newUser = User::factory()->create(['type' => 'staff']);

        $response = $this->postJson("/api/v1/sites/{$this->siteA->id}/users", [
            'user_id'    => $newUser->id,
            'role'       => 'staff',
            'is_default' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('user_sites', [
            'site_id' => $this->siteA->id,
            'user_id' => $newUser->id,
            'role'    => 'staff',
        ]);
    }

    public function test_siege_peut_retirer_un_user_dun_site(): void
    {
        Sanctum::actingAs($this->siege);

        $this->deleteJson("/api/v1/sites/{$this->siteA->id}/users/{$this->manager->id}")
            ->assertOk();

        $this->assertDatabaseMissing('user_sites', [
            'site_id' => $this->siteA->id,
            'user_id' => $this->manager->id,
        ]);
    }
}
