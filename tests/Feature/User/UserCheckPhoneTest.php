<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Vérifie l'endpoint POST /api/v1/users/check-phone
 */
class UserCheckPhoneTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('users.create', 'web');

        $this->staff = User::factory()->create(['type' => 'staff']);
        $this->staff->givePermissionTo('users.create');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Cas 1 : téléphone disponible → available = true
    // ──────────────────────────────────────────────────────────────────────

    public function test_phone_disponible_retourne_available_true(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/users/check-phone', [
            'phone'           => '+224620000001',
            'code_phone_pays' => '+224',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.normalized_phone', '+224620000001');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Cas 2 : téléphone déjà pris → available = false
    // ──────────────────────────────────────────────────────────────────────

    public function test_phone_deja_pris_retourne_available_false(): void
    {
        // Crée un utilisateur avec ce numéro (le mutator normalise à la création)
        User::factory()->create(['phone' => '+224620000002']);

        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/users/check-phone', [
            'phone'           => '+224620000002',
            'code_phone_pays' => '+224',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.normalized_phone', '+224620000002');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Cas 3 : numéro avec espaces/tirets → normalisé avant check
    // ──────────────────────────────────────────────────────────────────────

    public function test_phone_normalise_avant_check(): void
    {
        // Stocké normalisé grâce au mutator
        User::factory()->create(['phone' => '+224 62-000-00-03']);

        Sanctum::actingAs($this->staff);

        // Envoyé avec formatage différent mais identique après nettoyage
        $response = $this->postJson('/api/v1/users/check-phone', [
            'phone'           => '+224 62-000-00-03',
            'code_phone_pays' => '+224',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.normalized_phone', '+224620000003');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Cas 4 : phone manquant → 422
    // ──────────────────────────────────────────────────────────────────────

    public function test_phone_manquant_retourne_422(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/users/check-phone', [
            'code_phone_pays' => '+224',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Cas 5 : code_phone_pays manquant → 422
    // ──────────────────────────────────────────────────────────────────────

    public function test_code_phone_pays_manquant_retourne_422(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/users/check-phone', [
            'phone' => '+224620000004',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code_phone_pays']);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Cas 6 : non authentifié → 401
    // ──────────────────────────────────────────────────────────────────────

    public function test_non_authentifie_retourne_401(): void
    {
        $response = $this->postJson('/api/v1/users/check-phone', [
            'phone'           => '+224620000005',
            'code_phone_pays' => '+224',
        ]);

        $response->assertUnauthorized();
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Cas 7 : sans permission → 403
    // ──────────────────────────────────────────────────────────────────────

    public function test_sans_permission_retourne_403(): void
    {
        $staffSansPermission = User::factory()->create(['type' => 'staff']);
        Sanctum::actingAs($staffSansPermission);

        $response = $this->postJson('/api/v1/users/check-phone', [
            'phone'           => '+224620000006',
            'code_phone_pays' => '+224',
        ]);

        $response->assertForbidden();
    }
}
