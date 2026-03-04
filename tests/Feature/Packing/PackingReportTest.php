<?php

namespace Tests\Feature\Packing;

use App\Enums\PackingStatut;
use App\Enums\SiteRole;
use App\Enums\SiteType;
use App\Jobs\SendPackingReportJob;
use App\Models\Packing;
use App\Models\Prestataire;
use App\Models\Site;
use App\Models\User;
use App\Services\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tests du système de rapport packings.
 *
 * Couvre : export JSON, export PDF, envoi email (queue), filtres,
 *          isolation cross-usine, contrôle des permissions.
 */
class PackingReportTest extends TestCase
{
    use RefreshDatabase;

    private Site        $usine;
    private User        $staff;
    private Prestataire $prestataire;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('packings.read', 'web');

        $this->usine = Site::create([
            'nom'    => 'Site Report Test',
            'code'   => 'RPT-A',
            'type'   => SiteType::USINE->value,
            'statut' => 'active',
        ]);

        $this->staff = User::factory()->create([
            'type'            => 'staff',
            'default_site_id' => $this->usine->id,
        ]);

        $this->staff->sites()->attach($this->usine->id, [
            'role'       => SiteRole::MANAGER->value,
            'is_default' => true,
        ]);

        $this->staff->givePermissionTo('packings.read');

        app(SiteContext::class)->setCurrentSiteId($this->usine->id);

        $this->prestataire = Prestataire::create([
            'site_id'        => $this->usine->id,
            'nom'             => 'BARRY',
            'prenom'          => 'Ibrahima',
            'phone'           => '621999001',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'pays'            => 'Guinee',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Export JSON
    // ──────────────────────────────────────────────────────────────────────

    public function test_rapport_json_retourne_structure_correcte(): void
    {
        Packing::factory()->count(3)->create([
            'site_id'       => $this->usine->id,
            'prestataire_id' => $this->prestataire->id,
            'statut'         => PackingStatut::IMPAYEE->value,
        ]);

        Sanctum::actingAs($this->staff);

        $response = $this->withHeaders(['X-Site-Id' => $this->usine->id])
            ->getJson('/api/v1/packings/reports');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_packings', 3)
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['total_packings', 'total_rouleaux', 'total_montant', 'total_verse', 'total_restant'],
                    'packings',
                    'filters',
                ],
            ]);
    }

    public function test_rapport_json_filtre_par_statut(): void
    {
        Packing::factory()->create([
            'site_id' => $this->usine->id,
            'statut'   => PackingStatut::IMPAYEE->value,
        ]);
        Packing::factory()->create([
            'site_id' => $this->usine->id,
            'statut'   => PackingStatut::PAYEE->value,
        ]);

        Sanctum::actingAs($this->staff);

        $response = $this->withHeaders(['X-Site-Id' => $this->usine->id])
            ->getJson('/api/v1/packings/reports?statut=payee');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_packings', 1);
    }

    public function test_rapport_json_filtre_par_date(): void
    {
        Packing::factory()->create([
            'site_id' => $this->usine->id,
            'date'     => '2026-01-10',
        ]);
        Packing::factory()->create([
            'site_id' => $this->usine->id,
            'date'     => '2026-03-01',
        ]);

        Sanctum::actingAs($this->staff);

        $response = $this->withHeaders(['X-Site-Id' => $this->usine->id])
            ->getJson('/api/v1/packings/reports?date_from=2026-03-01&date_to=2026-03-31');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_packings', 1);
    }

    public function test_rapport_rejette_date_to_avant_date_from(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeaders(['X-Site-Id' => $this->usine->id])
            ->getJson('/api/v1/packings/reports?date_from=2026-03-31&date_to=2026-01-01');

        $response->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Export PDF (nécessite barryvdh/laravel-dompdf installé)
    // ──────────────────────────────────────────────────────────────────────

    public function test_rapport_pdf_retourne_content_type_pdf(): void
    {
        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $this->markTestSkipped('barryvdh/laravel-dompdf non installé. Lancer : composer require barryvdh/laravel-dompdf');
        }

        Packing::factory()->create(['site_id' => $this->usine->id]);

        Sanctum::actingAs($this->staff);

        $response = $this->withHeaders(['X-Site-Id' => $this->usine->id])
            ->get('/api/v1/packings/reports?format=pdf');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Envoi email (queue)
    // ──────────────────────────────────────────────────────────────────────

    public function test_rapport_email_dispatche_le_job_avec_les_bons_parametres(): void
    {
        Queue::fake();

        Sanctum::actingAs($this->staff);

        $response = $this->withHeaders(['X-Site-Id' => $this->usine->id])
            ->postJson('/api/v1/packings/reports/email', [
                'email'    => 'rapport@example.com',
                'statut'   => 'payee',
                'date_from'=> '2026-01-01',
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.email', 'rapport@example.com');

        Queue::assertPushed(SendPackingReportJob::class, function ($job) {
            return $job->recipientEmail === 'rapport@example.com'
                && $job->siteId === $this->usine->id
                && ($job->filters['statut'] ?? null) === 'payee';
        });
    }

    public function test_rapport_email_rejette_email_invalide(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeaders(['X-Site-Id' => $this->usine->id])
            ->postJson('/api/v1/packings/reports/email', [
                'email' => 'pas-un-email',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_rapport_email_champ_email_obligatoire(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->withHeaders(['X-Site-Id' => $this->usine->id])
            ->postJson('/api/v1/packings/reports/email', []);

        $response->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Isolation cross-usine
    // ──────────────────────────────────────────────────────────────────────

    public function test_rapport_nexpose_pas_les_packings_dune_autre_usine(): void
    {
        $autreUsine = Site::create([
            'nom'    => 'Autre Site',
            'code'   => 'RPT-B',
            'type'   => SiteType::USINE->value,
            'statut' => 'active',
        ]);

        // 2 packings dans l'autre usine
        Packing::factory()->count(2)->create(['site_id' => $autreUsine->id]);

        // 1 seul packing dans notre usine
        Packing::factory()->create(['site_id' => $this->usine->id]);

        Sanctum::actingAs($this->staff);

        $response = $this->withHeaders(['X-Site-Id' => $this->usine->id])
            ->getJson('/api/v1/packings/reports');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_packings', 1);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Permissions
    // ──────────────────────────────────────────────────────────────────────

    public function test_rapport_retourne_403_sans_permission(): void
    {
        $userSansPerm = User::factory()->create([
            'type'             => 'staff',
            'default_site_id' => $this->usine->id,
        ]);

        Sanctum::actingAs($userSansPerm);

        $response = $this->withHeaders(['X-Site-Id' => $this->usine->id])
            ->getJson('/api/v1/packings/reports');

        $response->assertStatus(403);
    }

    public function test_rapport_email_retourne_403_sans_permission(): void
    {
        Queue::fake();

        $userSansPerm = User::factory()->create([
            'type'             => 'staff',
            'default_site_id' => $this->usine->id,
        ]);

        Sanctum::actingAs($userSansPerm);

        $response = $this->withHeaders(['X-Site-Id' => $this->usine->id])
            ->postJson('/api/v1/packings/reports/email', ['email' => 'test@test.com']);

        $response->assertStatus(403);

        Queue::assertNothingPushed();
    }
}
