<?php

namespace Tests\Feature\Usine;

use App\Enums\UsineRole;
use App\Enums\UsineType;
use App\Models\Prestataire;
use App\Models\Usine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Vérifie que GET /api/v1/auth/me retourne correctement les infos multi-usines.
 */
class UsineMeEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Usine $siege;
    private Usine $usineA;

    protected function setUp(): void
    {
        parent::setUp();

        // Codes différents des codes du backfill (ELM-SIEGE, ELM-USN-01)
        $this->siege = Usine::create([
            'nom'    => 'ELM Siège Test',
            'code'   => 'ME-SIEGE',
            'type'   => UsineType::SIEGE,
            'statut' => 'active',
        ]);

        $this->usineA = Usine::create([
            'nom'       => 'Usine Alpha',
            'code'      => 'ME-A',
            'type'      => UsineType::USINE,
            'statut'    => 'active',
            'parent_id' => $this->siege->id,
        ]);
    }

    /** /me retourne les infos usine pour un user normal */
    public function test_me_retourne_infos_usine_pour_user_normal(): void
    {
        $user = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usineA->id,
        ]);

        $user->usines()->attach($this->usineA->id, [
            'role'       => UsineRole::MANAGER->value,
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'roles',
                    'permissions',
                    'accessible_usines',
                    'default_usine_id',
                    'current_usine_id',
                    'is_siege_user',
                ],
            ]);

        $data = $response->json('data');

        $this->assertEquals($this->usineA->id, $data['default_usine_id']);
        $this->assertFalse($data['is_siege_user']);
        $this->assertCount(1, $data['accessible_usines']);
        $this->assertEquals($this->usineA->id, $data['accessible_usines'][0]['id']);
        $this->assertEquals('manager', $data['accessible_usines'][0]['mon_role']);
    }

    /** /me retourne is_siege_user = true pour un utilisateur siège */
    public function test_me_retourne_is_siege_true_pour_user_siege(): void
    {
        $user = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usineA->id,
        ]);

        $user->usines()->attach($this->siege->id, [
            'role'       => UsineRole::OWNER_SIEGE->value,
            'is_default' => false,
        ]);

        $user->usines()->attach($this->usineA->id, [
            'role'       => UsineRole::MANAGER->value,
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk();
        $this->assertTrue($response->json('data.is_siege_user'));
        $this->assertCount(2, $response->json('data.accessible_usines'));
    }

    /** current_usine_id reflète le header X-Usine-Id */
    public function test_me_retourne_current_usine_id_depuis_header(): void
    {
        $usineB = Usine::create([
            'nom'    => 'Usine Beta',
            'code'   => 'ME-B',
            'type'   => UsineType::USINE,
            'statut' => 'active',
        ]);

        $user = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usineA->id,
        ]);

        $user->usines()->attach($this->siege->id, [
            'role'       => UsineRole::OWNER_SIEGE->value,
            'is_default' => false,
        ]);

        $user->usines()->attach($this->usineA->id, [
            'role'       => UsineRole::MANAGER->value,
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Usine-Id', (string) $usineB->id)
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $this->assertEquals($usineB->id, $response->json('data.current_usine_id'));
    }

    /** exists scopé par usine : unique téléphone scopé par usine */
    public function test_exists_unique_scopes_par_usine(): void
    {
        Permission::findOrCreate('prestataires.create', 'web');

        $usineB = Usine::create([
            'nom'    => 'Usine Beta 2',
            'code'   => 'ME-B2',
            'type'   => UsineType::USINE,
            'statut' => 'active',
        ]);

        $user = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usineA->id,
        ]);

        $user->usines()->attach($this->usineA->id, [
            'role'       => UsineRole::MANAGER->value,
            'is_default' => true,
        ]);

        $user->givePermissionTo('prestataires.create');

        Sanctum::actingAs($user);

        // Créer un prestataire dans usineB avec un certain numéro
        Prestataire::withoutGlobalScopes()->create([
            'usine_id'        => $usineB->id,
            'nom'             => 'DIALLO',
            'prenom'          => 'Mamadou',
            'phone'           => '+224699000001',
            'type'            => 'fournisseur',
            'pays'            => 'Guinee',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'reference'       => 'PREST-UNIQ-001',
        ]);

        // Tenter de créer le même numéro dans usineA → doit passer (unique scopé par usine)
        $response = $this->withHeader('X-Usine-Id', (string) $this->usineA->id)
            ->postJson('/api/v1/prestataires', [
                'nom'             => 'Diallo',
                'prenom'          => 'Ibrahima',
                'phone'           => '+224699000001', // même tel, mais dans usineA
                'type'            => 'fournisseur',
                'pays'            => 'Guinee',
                'code_pays'       => 'GN',
                'code_phone_pays' => '+224',
            ]);

        $response->assertCreated();
    }
}
