<?php

namespace Tests\Feature\Usine;

use App\Enums\UsineRole;
use App\Enums\UsineType;
use App\Models\Prestataire;
use App\Models\Usine;
use App\Models\User;
use App\Services\UsineContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Vérifie qu'un manager d'usine A ne peut pas lire les données de l'usine B.
 */
class UsineIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Usine $usineA;
    private Usine $usineB;
    private User  $managerA;
    private User  $managerB;
    private Prestataire $prestataireA;
    private Prestataire $prestataireB;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('prestataires.read', 'web');

        // Codes volontairement différents des codes générés par la migration de backfill
        $this->usineA = Usine::create([
            'nom'    => 'Usine Alpha',
            'code'   => 'ISO-A',
            'type'   => UsineType::USINE,
            'statut' => 'active',
        ]);

        $this->usineB = Usine::create([
            'nom'    => 'Usine Beta',
            'code'   => 'ISO-B',
            'type'   => UsineType::USINE,
            'statut' => 'active',
        ]);

        $this->managerA = $this->createManager('managerA@test.com', $this->usineA);
        $this->managerB = $this->createManager('managerB@test.com', $this->usineB);

        $this->prestataireA = $this->createPrestataire($this->usineA, '+224601000001');
        $this->prestataireB = $this->createPrestataire($this->usineB, '+224601000002');
    }

    /** Manager A ne voit pas les données usine B */
    public function test_manager_usine_a_ne_lit_pas_donnees_usine_b(): void
    {
        Sanctum::actingAs($this->managerA);

        app(UsineContext::class)->setCurrentUsineId($this->usineA->id);

        $response = $this->getJson('/api/v1/prestataires');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($this->prestataireA->id), 'Prestataire A devrait être visible');
        $this->assertFalse($ids->contains($this->prestataireB->id), 'Prestataire B ne devrait pas être visible');
    }

    /** Manager B ne voit pas les données usine A */
    public function test_manager_usine_b_ne_lit_pas_donnees_usine_a(): void
    {
        Sanctum::actingAs($this->managerB);
        app(UsineContext::class)->setCurrentUsineId($this->usineB->id);

        $response = $this->getJson('/api/v1/prestataires');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($this->prestataireA->id), 'Prestataire A ne devrait pas être visible');
        $this->assertTrue($ids->contains($this->prestataireB->id), 'Prestataire B devrait être visible');
    }

    /** Manager A est refusé s'il essaie d'accéder à l'usine B via header */
    public function test_manager_a_ne_peut_pas_acceder_usine_b_via_header(): void
    {
        Sanctum::actingAs($this->managerA);

        $response = $this->withHeader('X-Usine-Id', (string) $this->usineB->id)
            ->getJson('/api/v1/prestataires');

        $response->assertForbidden();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function createManager(string $email, Usine $usine): User
    {
        $user = User::factory()->create([
            'email'            => $email,
            'type'             => 'staff',
            'default_usine_id' => $usine->id,
        ]);

        $user->usines()->attach($usine->id, [
            'role'       => UsineRole::MANAGER->value,
            'is_default' => true,
        ]);

        $user->givePermissionTo('prestataires.read');

        return $user;
    }

    private function createPrestataire(Usine $usine, string $phone): Prestataire
    {
        return Prestataire::withoutGlobalScopes()->create([
            'usine_id'        => $usine->id,
            'nom'             => 'TEST',
            'prenom'          => 'User',
            'phone'           => $phone,
            'type'            => 'fournisseur',
            'pays'            => 'Guinee',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'reference'       => 'PREST-' . $usine->code . '-' . rand(100, 999),
        ]);
    }
}
