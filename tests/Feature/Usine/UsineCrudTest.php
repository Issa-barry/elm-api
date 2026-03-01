<?php

namespace Tests\Feature\Usine;

use App\Enums\UsineStatut;
use App\Enums\UsineType;
use App\Models\Usine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UsineCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $siege;
    private User $manager;
    private Usine $usineSiege;
    private Usine $usineA;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        foreach (['usines.create', 'usines.read', 'usines.update', 'usines.delete'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        // Usine siège
        $this->usineSiege = Usine::create([
            'nom'    => 'ELM Siège',
            'code'   => 'CRUD-SIEGE',
            'type'   => UsineType::SIEGE->value,
            'statut' => UsineStatut::ACTIVE->value,
        ]);

        // Usine normale
        $this->usineA = Usine::create([
            'nom'    => 'Usine Alpha',
            'code'   => 'CRUD-A',
            'type'   => UsineType::USINE->value,
            'statut' => UsineStatut::ACTIVE->value,
            'pays'   => 'Guinée',
            'ville'  => 'Conakry',
        ]);

        // User siège (owner_siege → isSiege() = true)
        $this->siege = User::factory()->create(['type' => 'staff', 'default_usine_id' => $this->usineSiege->id]);
        $this->siege->usines()->attach($this->usineSiege->id, ['role' => 'owner_siege', 'is_default' => true]);
        $this->siege->givePermissionTo(['usines.create', 'usines.read', 'usines.update', 'usines.delete']);

        // User manager d'usineA seulement
        $this->manager = User::factory()->create(['type' => 'staff', 'default_usine_id' => $this->usineA->id]);
        $this->manager->usines()->attach($this->usineA->id, ['role' => 'manager', 'is_default' => true]);
        $this->manager->givePermissionTo(['usines.read']);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  INDEX
    // ═══════════════════════════════════════════════════════════════════

    public function test_index_siege_voit_toutes_les_usines(): void
    {
        Sanctum::actingAs($this->siege);

        $response = $this->getJson('/api/v1/usines');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_index_manager_voit_seulement_ses_usines(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/usines');

        $response->assertOk();
        $codes = array_column($response->json('data'), 'code');
        $this->assertContains('CRUD-A', $codes);
        $this->assertNotContains('CRUD-SIEGE', $codes);
    }

    public function test_index_non_authentifie_retourne_401(): void
    {
        $this->getJson('/api/v1/usines')->assertUnauthorized();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  SHOW
    // ═══════════════════════════════════════════════════════════════════

    public function test_show_siege_peut_voir_nimporte_quelle_usine(): void
    {
        Sanctum::actingAs($this->siege);

        $response = $this->getJson("/api/v1/usines/{$this->usineA->id}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'CRUD-A')
            ->assertJsonPath('data.pays', 'Guinée')
            ->assertJsonPath('data.ville', 'Conakry');
    }

    public function test_show_manager_peut_voir_son_usine(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/usines/{$this->usineA->id}");

        $response->assertOk()->assertJsonPath('data.code', 'CRUD-A');
    }

    public function test_show_manager_ne_peut_pas_voir_usine_etrangere(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/usines/{$this->usineSiege->id}");

        $response->assertForbidden();
    }

    public function test_show_retourne_404_si_inexistante(): void
    {
        Sanctum::actingAs($this->siege);

        $this->getJson('/api/v1/usines/99999')->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STORE
    // ═══════════════════════════════════════════════════════════════════

    public function test_store_siege_cree_une_usine_avec_localisation(): void
    {
        Sanctum::actingAs($this->siege);

        $response = $this->postJson('/api/v1/usines', [
            'nom'       => 'Nouvelle Usine',
            'code'      => 'CRUD-NEW',
            'type'      => UsineType::USINE->value,
            'pays'      => 'Guinée',
            'ville'     => 'Kindia',
            'quartier'  => 'Centre-ville',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'CRUD-NEW')
            ->assertJsonPath('data.pays', 'Guinée')
            ->assertJsonPath('data.ville', 'Kindia')
            ->assertJsonPath('data.quartier', 'Centre-ville')
            ->assertJsonPath('data.statut', UsineStatut::ACTIVE->value);

        $this->assertDatabaseHas('usines', ['code' => 'CRUD-NEW', 'pays' => 'Guinée']);
    }

    public function test_store_non_siege_est_interdit(): void
    {
        Sanctum::actingAs($this->manager);

        $this->manager->givePermissionTo('usines.create');

        $response = $this->postJson('/api/v1/usines', [
            'nom'  => 'Tentative',
            'code' => 'CRUD-FAIL',
            'type' => UsineType::USINE->value,
        ]);

        $response->assertForbidden();
    }

    public function test_store_code_doit_etre_unique(): void
    {
        Sanctum::actingAs($this->siege);

        $this->postJson('/api/v1/usines', [
            'nom'  => 'Doublon',
            'code' => 'CRUD-A', // déjà existant
            'type' => UsineType::USINE->value,
        ])->assertUnprocessable();
    }

    public function test_store_code_doit_respecter_le_format(): void
    {
        Sanctum::actingAs($this->siege);

        $this->postJson('/api/v1/usines', [
            'nom'  => 'Mauvais code',
            'code' => 'crud a!',  // minuscules + espace + spécial
            'type' => UsineType::USINE->value,
        ])->assertUnprocessable();
    }

    public function test_store_avec_parent_id(): void
    {
        Sanctum::actingAs($this->siege);

        $response = $this->postJson('/api/v1/usines', [
            'nom'       => 'Sous-usine',
            'code'      => 'CRUD-SUB',
            'type'      => UsineType::USINE->value,
            'parent_id' => $this->usineSiege->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.parent_id', $this->usineSiege->id);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PATCH (UPDATE)
    // ═══════════════════════════════════════════════════════════════════

    public function test_patch_siege_met_a_jour_les_champs(): void
    {
        Sanctum::actingAs($this->siege);

        $response = $this->patchJson("/api/v1/usines/{$this->usineA->id}", [
            'pays'     => 'Sénégal',
            'ville'    => 'Dakar',
            'quartier' => 'Plateau',
            'statut'   => UsineStatut::INACTIVE->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.pays', 'Sénégal')
            ->assertJsonPath('data.ville', 'Dakar')
            ->assertJsonPath('data.quartier', 'Plateau')
            ->assertJsonPath('data.statut', UsineStatut::INACTIVE->value);
    }

    public function test_patch_non_siege_est_interdit(): void
    {
        Sanctum::actingAs($this->manager);

        $this->manager->givePermissionTo('usines.update');

        $this->patchJson("/api/v1/usines/{$this->usineA->id}", [
            'nom' => 'Tentative update',
        ])->assertForbidden();
    }

    public function test_patch_code_unique_ignore_propre_usine(): void
    {
        Sanctum::actingAs($this->siege);

        // Même code = OK (ignorer soi-même)
        $this->patchJson("/api/v1/usines/{$this->usineA->id}", [
            'code' => 'CRUD-A',
        ])->assertOk();

        // Code d'une autre usine = 422
        $this->patchJson("/api/v1/usines/{$this->usineA->id}", [
            'code' => 'CRUD-SIEGE',
        ])->assertUnprocessable();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  DESTROY
    // ═══════════════════════════════════════════════════════════════════

    public function test_destroy_siege_supprime_une_usine(): void
    {
        Sanctum::actingAs($this->siege);

        $usineTemp = Usine::create([
            'nom'  => 'Temp',
            'code' => 'CRUD-TMP',
            'type' => UsineType::USINE->value,
        ]);

        $this->deleteJson("/api/v1/usines/{$usineTemp->id}")
            ->assertOk();

        $this->assertSoftDeleted('usines', ['id' => $usineTemp->id]);
    }

    public function test_destroy_siege_ne_peut_pas_supprimer_usine_siege(): void
    {
        Sanctum::actingAs($this->siege);

        $this->deleteJson("/api/v1/usines/{$this->usineSiege->id}")
            ->assertForbidden();
    }

    public function test_destroy_non_siege_est_interdit(): void
    {
        Sanctum::actingAs($this->manager);

        $this->manager->givePermissionTo('usines.delete');

        $this->deleteJson("/api/v1/usines/{$this->usineA->id}")
            ->assertForbidden();
    }

    public function test_destroy_retourne_404_si_inexistante(): void
    {
        Sanctum::actingAs($this->siege);

        $this->deleteJson('/api/v1/usines/99999')->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  USERS D'UNE USINE
    // ═══════════════════════════════════════════════════════════════════

    public function test_users_siege_voit_les_users_dune_usine(): void
    {
        Sanctum::actingAs($this->siege);

        $response = $this->getJson("/api/v1/usines/{$this->usineA->id}/users");

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($this->manager->id, $ids);
    }

    public function test_users_manager_voit_les_users_de_son_usine(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/usines/{$this->usineA->id}/users");

        $response->assertOk();
        // Chaque user doit avoir role_usine et is_default
        $user = collect($response->json('data'))->firstWhere('id', $this->manager->id);
        $this->assertNotNull($user);
        $this->assertEquals('manager', $user['role_usine']);
        $this->assertTrue($user['is_default']);
    }

    public function test_users_manager_ne_peut_pas_voir_usine_etrangere(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/usines/{$this->usineSiege->id}/users")
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  AFFECTATION USER ↔ USINE
    // ═══════════════════════════════════════════════════════════════════

    public function test_siege_peut_affecter_un_user_a_une_usine(): void
    {
        Sanctum::actingAs($this->siege);

        $newUser = User::factory()->create(['type' => 'staff']);

        $response = $this->postJson("/api/v1/usines/{$this->usineA->id}/users", [
            'user_id'    => $newUser->id,
            'role'       => 'staff',
            'is_default' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('user_usines', [
            'usine_id' => $this->usineA->id,
            'user_id'  => $newUser->id,
            'role'     => 'staff',
        ]);
    }

    public function test_siege_peut_retirer_un_user_dune_usine(): void
    {
        Sanctum::actingAs($this->siege);

        $this->deleteJson("/api/v1/usines/{$this->usineA->id}/users/{$this->manager->id}")
            ->assertOk();

        $this->assertDatabaseMissing('user_usines', [
            'usine_id' => $this->usineA->id,
            'user_id'  => $this->manager->id,
        ]);
    }
}
