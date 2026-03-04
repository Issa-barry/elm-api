<?php

namespace Tests\Feature\Site;

use App\Enums\SiteRole;
use App\Enums\SiteType;
use App\Models\Prestataire;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Vérifie qu'un manager de site A ne peut pas lire les données du site B.
 */
class SiteIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Site $siteA;
    private Site $siteB;
    private User  $managerA;
    private User  $managerB;
    private Prestataire $prestataireA;
    private Prestataire $prestataireB;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('prestataires.read', 'web');

        // Codes volontairement différents des codes générés par la migration de backfill
        $this->siteA = Site::create([
            'nom'    => 'Site Alpha',
            'code'   => 'ISO-A',
            'type'   => SiteType::USINE,
            'statut' => 'active',
        ]);

        $this->siteB = Site::create([
            'nom'    => 'Site Beta',
            'code'   => 'ISO-B',
            'type'   => SiteType::USINE,
            'statut' => 'active',
        ]);

        $this->managerA = $this->createManager('managerA@test.com', $this->siteA);
        $this->managerB = $this->createManager('managerB@test.com', $this->siteB);

        $this->prestataireA = $this->createPrestataire($this->siteA, '+224601000001');
        $this->prestataireB = $this->createPrestataire($this->siteB, '+224601000002');
    }

    /** Manager A ne voit pas les données site B */
    public function test_manager_site_a_ne_lit_pas_donnees_site_b(): void
    {
        Sanctum::actingAs($this->managerA);

        app(SiteContext::class)->setCurrentSiteId($this->siteA->id);

        $response = $this->getJson('/api/v1/prestataires');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($this->prestataireA->id), 'Prestataire A devrait être visible');
        $this->assertFalse($ids->contains($this->prestataireB->id), 'Prestataire B ne devrait pas être visible');
    }

    /** Manager B ne voit pas les données site A */
    public function test_manager_site_b_ne_lit_pas_donnees_site_a(): void
    {
        Sanctum::actingAs($this->managerB);
        app(SiteContext::class)->setCurrentSiteId($this->siteB->id);

        $response = $this->getJson('/api/v1/prestataires');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($this->prestataireA->id), 'Prestataire A ne devrait pas être visible');
        $this->assertTrue($ids->contains($this->prestataireB->id), 'Prestataire B devrait être visible');
    }

    /** Manager A est refusé s'il essaie d'accéder au site B via header */
    public function test_manager_a_ne_peut_pas_acceder_site_b_via_header(): void
    {
        Sanctum::actingAs($this->managerA);

        $response = $this->withHeader('X-Site-Id', (string) $this->siteB->id)
            ->getJson('/api/v1/prestataires');

        $response->assertForbidden();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function createManager(string $email, Site $site): User
    {
        $user = User::factory()->create([
            'email'           => $email,
            'type'            => 'staff',
            'default_site_id' => $site->id,
        ]);

        $user->sites()->attach($site->id, [
            'role'       => SiteRole::MANAGER->value,
            'is_default' => true,
        ]);

        $user->givePermissionTo('prestataires.read');

        return $user;
    }

    private function createPrestataire(Site $site, string $phone): Prestataire
    {
        return Prestataire::withoutGlobalScopes()->create([
            'site_id'         => $site->id,
            'nom'             => 'TEST',
            'prenom'          => 'User',
            'phone'           => $phone,
            'type'            => 'fournisseur',
            'pays'            => 'Guinee',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'reference'       => 'PREST-' . $site->code . '-' . rand(100, 999),
        ]);
    }
}
