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
 * Vérifie que le siège peut basculer de contexte site via X-Site-Id.
 * Et que site_id est auto-rempli à la création.
 */
class SiteContextSwitchTest extends TestCase
{
    use RefreshDatabase;

    private Site $siege;
    private Site $siteA;
    private Site $siteB;
    private User  $siegeUser;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('prestataires.read', 'web');
        Permission::findOrCreate('prestataires.create', 'web');

        // Codes différents des codes du backfill (ELM-SIEGE, ELM-USN-01)
        $this->siege = Site::create([
            'nom'    => 'ELM Siège Test',
            'code'   => 'CTX-SIEGE',
            'type'   => SiteType::SIEGE,
            'statut' => 'active',
        ]);

        $this->siteA = Site::create([
            'nom'       => 'Site Alpha',
            'code'      => 'CTX-A',
            'type'      => SiteType::USINE,
            'statut'    => 'active',
            'parent_id' => $this->siege->id,
        ]);

        $this->siteB = Site::create([
            'nom'       => 'Site Beta',
            'code'      => 'CTX-B',
            'type'      => SiteType::USINE,
            'statut'    => 'active',
            'parent_id' => $this->siege->id,
        ]);

        $this->siegeUser = User::factory()->create([
            'type'            => 'staff',
            'default_site_id' => $this->siteA->id,
        ]);

        $this->siegeUser->sites()->attach($this->siege->id, [
            'role'       => SiteRole::OWNER_SIEGE->value,
            'is_default' => false,
        ]);

        $this->siegeUser->sites()->attach($this->siteA->id, [
            'role'       => SiteRole::MANAGER->value,
            'is_default' => true,
        ]);

        $this->siegeUser->givePermissionTo(['prestataires.read', 'prestataires.create']);
    }

    /** Siège peut basculer vers site B via X-Site-Id */
    public function test_siege_peut_changer_contexte_via_header(): void
    {
        $prestataireA = Prestataire::withoutGlobalScopes()->create($this->prestatairePayload($this->siteA, '+224601000001'));
        $prestataireB = Prestataire::withoutGlobalScopes()->create($this->prestatairePayload($this->siteB, '+224601000002'));

        Sanctum::actingAs($this->siegeUser);

        // Contexte site A
        $responseA = $this->withHeader('X-Site-Id', (string) $this->siteA->id)
            ->getJson('/api/v1/prestataires');

        $responseA->assertOk();
        $idsA = collect($responseA->json('data'))->pluck('id');
        $this->assertTrue($idsA->contains($prestataireA->id));
        $this->assertFalse($idsA->contains($prestataireB->id));

        // Basculer vers site B
        $responseB = $this->withHeader('X-Site-Id', (string) $this->siteB->id)
            ->getJson('/api/v1/prestataires');

        $responseB->assertOk();
        $idsB = collect($responseB->json('data'))->pluck('id');
        $this->assertFalse($idsB->contains($prestataireA->id));
        $this->assertTrue($idsB->contains($prestataireB->id));
    }

    /** Sans X-Site-Id et sans default_site_id, le siège voit tout (vue consolidée) */
    public function test_siege_sans_header_voit_toutes_les_donnees(): void
    {
        $prestataireA = Prestataire::withoutGlobalScopes()->create($this->prestatairePayload($this->siteA, '+224601000003'));
        $prestataireB = Prestataire::withoutGlobalScopes()->create($this->prestatairePayload($this->siteB, '+224601000004'));

        Sanctum::actingAs($this->siegeUser);

        // Pour tester la vue consolidée, pas de default_site_id
        $this->siegeUser->update(['default_site_id' => null]);

        $response = $this->getJson('/api/v1/prestataires');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($prestataireA->id), 'Prestataire A devrait être visible en vue consolidée');
        $this->assertTrue($ids->contains($prestataireB->id), 'Prestataire B devrait être visible en vue consolidée');
    }

    /** site_id est auto-rempli à la création selon X-Site-Id */
    public function test_site_id_auto_rempli_a_la_creation(): void
    {
        Sanctum::actingAs($this->siegeUser);

        $response = $this->withHeader('X-Site-Id', (string) $this->siteB->id)
            ->postJson('/api/v1/prestataires', [
                'nom'             => 'Diallo',
                'prenom'          => 'Mamadou',
                'phone'           => '+224620000010',
                'type'            => 'fournisseur',
                'pays'            => 'Guinee',
                'code_pays'       => 'GN',
                'code_phone_pays' => '+224',
            ]);

        $response->assertCreated();
        $this->assertEquals($this->siteB->id, $response->json('data.site_id'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function prestatairePayload(Site $site, string $phone): array
    {
        return [
            'site_id'         => $site->id,
            'nom'             => 'TEST',
            'prenom'          => 'User',
            'phone'           => $phone,
            'type'            => 'fournisseur',
            'pays'            => 'Guinee',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'reference'       => 'PREST-' . $site->code . '-' . rand(1000, 9999),
        ];
    }
}
