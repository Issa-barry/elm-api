<?php

namespace Tests\Feature\Site;

use App\Enums\SiteRole;
use App\Enums\SiteType;
use App\Models\Prestataire;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Vérifie que GET /api/v1/auth/me retourne correctement les infos multi-sites.
 */
class SiteMeEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Site $siege;
    private Site $siteA;

    protected function setUp(): void
    {
        parent::setUp();

        // Codes différents des codes du backfill (ELM-SIEGE, ELM-USN-01)
        $this->siege = Site::create([
            'nom'    => 'ELM Siège Test',
            'code'   => 'ME-SIEGE',
            'type'   => SiteType::SIEGE,
            'statut' => 'active',
        ]);

        $this->siteA = Site::create([
            'nom'       => 'Site Alpha',
            'code'      => 'ME-A',
            'type'      => SiteType::USINE,
            'statut'    => 'active',
            'parent_id' => $this->siege->id,
        ]);
    }

    /** /me retourne les infos site pour un user normal */
    public function test_me_retourne_infos_site_pour_user_normal(): void
    {
        $user = User::factory()->create([
            'type'            => 'staff',
            'default_site_id' => $this->siteA->id,
        ]);

        $user->sites()->attach($this->siteA->id, [
            'role'       => SiteRole::MANAGER->value,
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
                    'accessible_sites',
                    'default_site_id',
                    'current_site_id',
                    'is_siege_user',
                ],
            ]);

        $data = $response->json('data');

        $this->assertEquals($this->siteA->id, $data['default_site_id']);
        $this->assertFalse($data['is_siege_user']);
        $this->assertCount(1, $data['accessible_sites']);
        $this->assertEquals($this->siteA->id, $data['accessible_sites'][0]['id']);
        $this->assertEquals('manager', $data['accessible_sites'][0]['mon_role']);
    }

    /** /me retourne is_siege_user = true pour un utilisateur siège */
    public function test_me_retourne_is_siege_true_pour_user_siege(): void
    {
        $user = User::factory()->create([
            'type'            => 'staff',
            'default_site_id' => $this->siteA->id,
        ]);

        $user->sites()->attach($this->siege->id, [
            'role'       => SiteRole::OWNER_SIEGE->value,
            'is_default' => false,
        ]);

        $user->sites()->attach($this->siteA->id, [
            'role'       => SiteRole::MANAGER->value,
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk();
        $this->assertTrue($response->json('data.is_siege_user'));
        $this->assertCount(2, $response->json('data.accessible_sites'));
    }

    /** current_site_id reflète le header X-Site-Id */
    public function test_me_retourne_current_site_id_depuis_header(): void
    {
        $siteB = Site::create([
            'nom'    => 'Site Beta',
            'code'   => 'ME-B',
            'type'   => SiteType::USINE,
            'statut' => 'active',
        ]);

        $user = User::factory()->create([
            'type'            => 'staff',
            'default_site_id' => $this->siteA->id,
        ]);

        $user->sites()->attach($this->siege->id, [
            'role'       => SiteRole::OWNER_SIEGE->value,
            'is_default' => false,
        ]);

        $user->sites()->attach($this->siteA->id, [
            'role'       => SiteRole::MANAGER->value,
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Site-Id', (string) $siteB->id)
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $this->assertEquals($siteB->id, $response->json('data.current_site_id'));
    }

    /** exists scopé par site : unique téléphone scopé par site */
    public function test_exists_unique_scopes_par_site(): void
    {
        Permission::findOrCreate('prestataires.create', 'web');

        $siteB = Site::create([
            'nom'    => 'Site Beta 2',
            'code'   => 'ME-B2',
            'type'   => SiteType::USINE,
            'statut' => 'active',
        ]);

        $user = User::factory()->create([
            'type'            => 'staff',
            'default_site_id' => $this->siteA->id,
        ]);

        $user->sites()->attach($this->siteA->id, [
            'role'       => SiteRole::MANAGER->value,
            'is_default' => true,
        ]);

        $user->givePermissionTo('prestataires.create');

        Sanctum::actingAs($user);

        // Créer un prestataire dans siteB avec un certain numéro
        Prestataire::withoutGlobalScopes()->create([
            'site_id'         => $siteB->id,
            'nom'             => 'DIALLO',
            'prenom'          => 'Mamadou',
            'phone'           => '+224699000001',
            'type'            => 'fournisseur',
            'pays'            => 'Guinee',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'reference'       => 'PREST-UNIQ-001',
        ]);

        // Tenter de créer le même numéro dans siteA → doit passer (unique scopé par site)
        $response = $this->withHeader('X-Site-Id', (string) $this->siteA->id)
            ->postJson('/api/v1/prestataires', [
                'nom'             => 'Diallo',
                'prenom'          => 'Ibrahima',
                'phone'           => '+224699000001', // même tel, mais dans siteA
                'type'            => 'fournisseur',
                'pays'            => 'Guinee',
                'code_pays'       => 'GN',
                'code_phone_pays' => '+224',
            ]);

        $response->assertCreated();
    }
}
