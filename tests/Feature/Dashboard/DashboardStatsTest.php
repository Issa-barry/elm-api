<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UsineType;
use App\Enums\UserType;
use App\Enums\UsineRole;
use App\Models\Parametre;
use App\Models\Prestataire;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Usine;
use App\Models\User;
use App\Models\Vehicule;
use App\Services\UsineContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tests du endpoint GET /api/v1/dashboard/stats.
 *
 * Couvre :
 *  - Structure JSON attendue
 *  - Filtres de période (this_month, last_x_days, this_week, etc.)
 *  - Comportement avec/sans contexte usine
 *  - Division par zéro quand période précédente = 0
 */
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private Usine  $usine;
    private User   $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('prestataires.read', 'web');

        $this->usine = Usine::create([
            'nom'    => 'Usine Dashboard Test',
            'code'   => 'DSH-A',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        $this->staff = User::factory()->create([
            'type'             => 'staff',
            'default_usine_id' => $this->usine->id,
        ]);

        $this->staff->usines()->attach($this->usine->id, [
            'role'       => UsineRole::MANAGER->value,
            'is_default' => true,
        ]);

        $this->staff->givePermissionTo('prestataires.read');
    }

    // ──────────────────────────────────────────────────────────────────────
    // 1. Structure JSON et statut 200
    // ──────────────────────────────────────────────────────────────────────

    public function test_retourne_200_avec_structure_json_correcte(): void
    {
        Sanctum::actingAs($this->staff);
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        $response = $this->getJson('/api/v1/dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'period' => ['key', 'from', 'to'],
                    'prestataires'   => ['value', 'delta_pct', 'trend', 'sparkline'],
                    'utilisateurs'   => ['value', 'delta_pct', 'trend', 'sparkline'],
                    'vehicules'      => ['value', 'delta_pct', 'trend', 'sparkline'],
                    'rouleaux_stock' => ['value', 'delta_pct', 'trend', 'sparkline'],
                ],
            ])
            ->assertJson(['success' => true]);

        $data = $response->json('data');

        $this->assertSame('this_month', $data['period']['key']);

        // sparkline has exactly 7 points for each metric
        foreach (['prestataires', 'utilisateurs', 'vehicules', 'rouleaux_stock'] as $key) {
            $this->assertCount(7, $data[$key]['sparkline'], "{$key}.sparkline should have 7 points");
            $this->assertContains($data[$key]['trend'], ['up', 'down', 'flat']);
        }
    }

    public function test_non_authentifie_retourne_401(): void
    {
        $response = $this->getJson('/api/v1/dashboard/stats');

        $response->assertUnauthorized();
    }

    public function test_periode_invalide_retourne_422(): void
    {
        Sanctum::actingAs($this->staff);
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        $response = $this->getJson('/api/v1/dashboard/stats?period=invalid_period');

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 2. Filtres de période
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @dataProvider periodProvider
     */
    public function test_filtre_de_periode(string $period): void
    {
        Sanctum::actingAs($this->staff);
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        $response = $this->getJson("/api/v1/dashboard/stats?period={$period}");

        $response->assertOk()
            ->assertJsonPath('data.period.key', $period);
    }

    public static function periodProvider(): array
    {
        return [
            ['today'],
            ['yesterday'],
            ['this_week'],
            ['last_week'],
            ['this_month'],
            ['last_month'],
            ['this_year'],
            ['last_year'],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // 3. period=last_x_days
    // ──────────────────────────────────────────────────────────────────────

    public function test_last_x_days_utilise_le_parametre_days(): void
    {
        Sanctum::actingAs($this->staff);
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        $response = $this->getJson('/api/v1/dashboard/stats?period=last_x_days&days=14');

        $response->assertOk()
            ->assertJsonPath('data.period.key', 'last_x_days');

        $from = Carbon::parse($response->json('data.period.from'));
        $to   = Carbon::parse($response->json('data.period.to'));

        // period spans ~14 days (from ~14 days ago to today)
        $this->assertGreaterThanOrEqual(13, $from->diffInDays($to));
        $this->assertLessThanOrEqual(15, $from->diffInDays($to));
    }

    public function test_last_x_days_sans_days_utilise_30_par_defaut(): void
    {
        Sanctum::actingAs($this->staff);
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        $response = $this->getJson('/api/v1/dashboard/stats?period=last_x_days');

        $response->assertOk();

        $from = Carbon::parse($response->json('data.period.from'));
        $to   = Carbon::parse($response->json('data.period.to'));

        $this->assertGreaterThanOrEqual(29, $from->diffInDays($to));
    }

    public function test_last_x_days_avec_days_zero_retourne_422(): void
    {
        Sanctum::actingAs($this->staff);
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        $response = $this->getJson('/api/v1/dashboard/stats?period=last_x_days&days=0');

        $response->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────────────────
    // 4. Comptage avec contexte usine
    // ──────────────────────────────────────────────────────────────────────

    public function test_avec_contexte_usine_les_valeurs_sont_filtrees(): void
    {
        // Second usine with its own prestataire
        $usineB = Usine::create([
            'nom'    => 'Usine B Dashboard',
            'code'   => 'DSH-B',
            'type'   => UsineType::USINE->value,
            'statut' => 'active',
        ]);

        // Create prestataire in usineA (context will be set to usineA)
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        Prestataire::withoutGlobalScopes()->create([
            'usine_id' => $this->usine->id,
            'nom'      => 'PREST-A',
            'prenom'   => 'Test',
            'phone'    => '+224620000001',
            'type'     => 'fournisseur',
            'pays'     => 'Guinee',
            'code_pays' => 'GN',
            'code_phone_pays' => '+224',
            'ville'    => 'Conakry',
            'quartier' => 'Kaloum',
        ]);

        // Create prestataire in usineB (should NOT appear in usineA context)
        Prestataire::withoutGlobalScopes()->create([
            'usine_id' => $usineB->id,
            'nom'      => 'PREST-B',
            'prenom'   => 'Test',
            'phone'    => '+224620000002',
            'type'     => 'fournisseur',
            'pays'     => 'Guinee',
            'code_pays' => 'GN',
            'code_phone_pays' => '+224',
            'ville'    => 'Conakry',
            'quartier' => 'Kaloum',
        ]);

        Sanctum::actingAs($this->staff);

        $response = $this->getJson('/api/v1/dashboard/stats');

        $response->assertOk();

        // UsineA context → prestataires count = 1 (only usineA)
        $this->assertSame(1, $response->json('data.prestataires.value'));
    }

    public function test_sans_contexte_usine_retourne_la_vue_globale(): void
    {
        // No usine context → count across all usines
        app(UsineContext::class)->setCurrentUsineId(null);

        Prestataire::withoutGlobalScopes()->create([
            'usine_id' => $this->usine->id,
            'nom'      => 'GLOBAL-PREST',
            'prenom'   => 'Test',
            'phone'    => '+224620000010',
            'type'     => 'fournisseur',
            'pays'     => 'Guinee',
            'code_pays' => 'GN',
            'code_phone_pays' => '+224',
            'ville'    => 'Conakry',
            'quartier' => 'Kaloum',
        ]);

        Sanctum::actingAs($this->staff);

        $response = $this->getJson('/api/v1/dashboard/stats');

        $response->assertOk();

        // Without scope: should see all prestataires
        $this->assertGreaterThanOrEqual(1, $response->json('data.prestataires.value'));
    }

    // ──────────────────────────────────────────────────────────────────────
    // 5. Division par zéro — période précédente vide
    // ──────────────────────────────────────────────────────────────────────

    public function test_delta_est_null_quand_periode_precedente_est_vide(): void
    {
        Sanctum::actingAs($this->staff);
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        // Empty DB → previous period count = 0 → delta_pct must be null, not a division error
        $response = $this->getJson('/api/v1/dashboard/stats?period=this_month');

        $response->assertOk();

        // All metrics have 0 in previous period → delta_pct is null
        foreach (['prestataires', 'utilisateurs', 'vehicules'] as $key) {
            $this->assertNull(
                $response->json("data.{$key}.delta_pct"),
                "{$key}.delta_pct should be null when previous period is empty"
            );
            $this->assertSame('flat', $response->json("data.{$key}.trend"));
        }
    }

    public function test_trend_est_up_quand_periode_actuelle_superieure(): void
    {
        Sanctum::actingAs($this->staff);
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        $lastMonth = Carbon::now()->subMonth()->startOfMonth()->addDays(5)->toDateTimeString();
        $thisMonth = Carbon::now()->startOfMonth()->addDays(3)->toDateTimeString();

        // Create 1 prestataire in previous period, then backdate created_at via DB
        $p = Prestataire::withoutGlobalScopes()->create([
            'usine_id'        => $this->usine->id,
            'nom'             => 'PREST-PREV',
            'prenom'          => 'Test',
            'phone'           => '+224620000020',
            'type'            => 'fournisseur',
            'pays'            => 'Guinee',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'ville'           => 'Conakry',
            'quartier'        => 'Kaloum',
        ]);
        \DB::table('prestataires')->where('id', $p->id)->update(['created_at' => $lastMonth]);

        // Create 3 prestataires in current period
        foreach (range(1, 3) as $i) {
            $p = Prestataire::withoutGlobalScopes()->create([
                'usine_id'        => $this->usine->id,
                'nom'             => "PREST-CURR-{$i}",
                'prenom'          => 'Test',
                'phone'           => "+22462000003{$i}",
                'type'            => 'fournisseur',
                'pays'            => 'Guinee',
                'code_pays'       => 'GN',
                'code_phone_pays' => '+224',
                'ville'           => 'Conakry',
                'quartier'        => 'Kaloum',
            ]);
            \DB::table('prestataires')->where('id', $p->id)->update(['created_at' => $thisMonth]);
        }

        $response = $this->getJson('/api/v1/dashboard/stats?period=this_month');

        $response->assertOk();

        // 3 this month vs 1 last month → delta > 0 → trend = up
        $this->assertSame('up', $response->json('data.prestataires.trend'));
        $this->assertGreaterThan(0, $response->json('data.prestataires.delta_pct'));
    }

    public function test_trend_est_down_quand_periode_actuelle_inferieure(): void
    {
        Sanctum::actingAs($this->staff);
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        $lastMonth = Carbon::now()->subMonth()->startOfMonth()->addDays(5)->toDateTimeString();
        $thisMonth = Carbon::now()->startOfMonth()->addDays(3)->toDateTimeString();

        foreach (range(1, 5) as $i) {
            $p = Prestataire::withoutGlobalScopes()->create([
                'usine_id'        => $this->usine->id,
                'nom'             => "PREST-PREV-{$i}",
                'prenom'          => 'Test',
                'phone'           => "+22462000004{$i}",
                'type'            => 'fournisseur',
                'pays'            => 'Guinee',
                'code_pays'       => 'GN',
                'code_phone_pays' => '+224',
                'ville'           => 'Conakry',
                'quartier'        => 'Kaloum',
            ]);
            \DB::table('prestataires')->where('id', $p->id)->update(['created_at' => $lastMonth]);
        }

        // Only 1 prestataire this month
        $p = Prestataire::withoutGlobalScopes()->create([
            'usine_id'        => $this->usine->id,
            'nom'             => 'PREST-CURR',
            'prenom'          => 'Test',
            'phone'           => '+224620000050',
            'type'            => 'fournisseur',
            'pays'            => 'Guinee',
            'code_pays'       => 'GN',
            'code_phone_pays' => '+224',
            'ville'           => 'Conakry',
            'quartier'        => 'Kaloum',
        ]);
        \DB::table('prestataires')->where('id', $p->id)->update(['created_at' => $thisMonth]);

        $response = $this->getJson('/api/v1/dashboard/stats?period=this_month');

        $response->assertOk();

        // 1 this month vs 5 last month → delta < 0 → trend = down
        $this->assertSame('down', $response->json('data.prestataires.trend'));
        $this->assertLessThan(0, $response->json('data.prestataires.delta_pct'));
    }

    // ──────────────────────────────────────────────────────────────────────
    // 6. rouleaux_stock
    // ──────────────────────────────────────────────────────────────────────

    public function test_rouleaux_stock_retourne_zero_si_pas_de_produit_configure(): void
    {
        Sanctum::actingAs($this->staff);
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        // Ensure no rouleau product is configured (cache already cleared by RefreshDatabase)
        $response = $this->getJson('/api/v1/dashboard/stats');

        $response->assertOk();

        $rouleau = $response->json('data.rouleaux_stock');
        $this->assertSame(0, $rouleau['value']);
        $this->assertNull($rouleau['delta_pct']);
        $this->assertSame('flat', $rouleau['trend']);
        $this->assertCount(7, $rouleau['sparkline']);
    }

    public function test_rouleaux_stock_retourne_somme_du_stock_correct(): void
    {
        Sanctum::actingAs($this->staff);
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        // Create rouleau product and set it as the configured product
        app(UsineContext::class)->setCurrentUsineId(null); // disable scope for product creation
        $produit = Produit::create([
            'nom'        => 'Rouleau Test',
            'code'       => 'DSH-ROULEAU-01',
            'type'       => 'materiel',
            'statut'     => 'actif',
            'prix_achat' => 500,
            'prix_vente' => 700,
            'is_global'  => true,
            'usine_id'   => null,
        ]);
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        // Create stock for this usine
        Stock::create([
            'produit_id' => $produit->id,
            'usine_id'   => $this->usine->id,
            'qte_stock'  => 42,
        ]);

        // Set the parametre (bypass Cache — write directly to DB)
        \DB::table('parametres')->updateOrInsert(
            ['cle' => Parametre::CLE_PRODUIT_ROULEAU_ID],
            [
                'valeur'      => (string) $produit->id,
                'type'        => 'integer',
                'groupe'      => 'packing',
                'description' => 'Test rouleau id',
            ]
        );
        \Illuminate\Support\Facades\Cache::forget('parametre_' . Parametre::CLE_PRODUIT_ROULEAU_ID);

        $response = $this->getJson('/api/v1/dashboard/stats');

        $response->assertOk();

        $this->assertSame(42, $response->json('data.rouleaux_stock.value'));
    }

    // ──────────────────────────────────────────────────────────────────────
    // 7. Utilisateurs — comptage par usine
    // ──────────────────────────────────────────────────────────────────────

    public function test_utilisateurs_compte_uniquement_les_users_de_lusine_courante(): void
    {
        Sanctum::actingAs($this->staff);
        app(UsineContext::class)->setCurrentUsineId($this->usine->id);

        // staff user already attached to $this->usine in setUp (1 user)
        // Add another user NOT attached to this usine
        User::factory()->create(['type' => 'staff']);

        $response = $this->getJson('/api/v1/dashboard/stats');

        $response->assertOk();

        // Only the manager from setUp is attached to this usine
        $this->assertSame(1, $response->json('data.utilisateurs.value'));
    }
}
