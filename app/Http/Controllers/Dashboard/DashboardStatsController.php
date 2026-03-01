<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\TypeVehicule;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use App\Models\Parametre;
use App\Models\Prestataire;
use App\Models\Stock;
use App\Models\User;
use App\Models\Vehicule;
use App\Services\UsineContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardStatsController extends Controller
{
    use ApiResponse;

    private const ALLOWED_PERIODS = [
        'today', 'yesterday',
        'this_week', 'last_week',
        'this_month', 'last_month',
        'this_year', 'last_year',
        'last_x_days',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $period = $request->get('period', 'this_month');

        if (! in_array($period, self::ALLOWED_PERIODS)) {
            return $this->errorResponse(
                'Période invalide. Valeurs acceptées : ' . implode(', ', self::ALLOWED_PERIODS),
                null,
                422
            );
        }

        $days = null;
        if ($period === 'last_x_days') {
            $days = (int) $request->get('days', 30);
            if ($days < 1) {
                return $this->errorResponse('Le paramètre days doit être un entier > 0.', null, 422);
            }
        }

        [$from, $to, $prevFrom, $prevTo] = $this->resolvePeriod($period, $days);

        $usineId = app(UsineContext::class)->getCurrentUsineId();

        $data = [
            'period' => [
                'key'  => $period,
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'prestataires'       => $this->buildEntityStat(Prestataire::class, $from, $to, $prevFrom, $prevTo),
            'utilisateurs'       => $this->buildUserStat($from, $to, $prevFrom, $prevTo, $usineId),
            'vehicules'          => $this->buildEntityStat(Vehicule::class, $from, $to, $prevFrom, $prevTo),
            'rouleaux_stock'     => $this->buildRouleauxStat($from, $to, $prevFrom, $prevTo, $usineId),
            'vehicules_par_type' => $this->buildVehiculesByType(),
        ];

        return $this->successResponse($data, 'Statistiques dashboard récupérées avec succès');
    }

    // ── Period resolution ──────────────────────────────────────────────────

    /**
     * Resolve a period key into [from, to, prevFrom, prevTo] Carbon instances.
     */
    private function resolvePeriod(string $period, ?int $days): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [
                Carbon::today()->startOfDay(),
                Carbon::today()->endOfDay(),
                Carbon::yesterday()->startOfDay(),
                Carbon::yesterday()->endOfDay(),
            ],
            'yesterday' => [
                Carbon::yesterday()->startOfDay(),
                Carbon::yesterday()->endOfDay(),
                $now->copy()->subDays(2)->startOfDay(),
                $now->copy()->subDays(2)->endOfDay(),
            ],
            'this_week' => [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
            ],
            'last_week' => [
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
                $now->copy()->subWeeks(2)->startOfWeek(),
                $now->copy()->subWeeks(2)->endOfWeek(),
            ],
            'this_month' => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            'last_month' => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
                $now->copy()->subMonths(2)->startOfMonth(),
                $now->copy()->subMonths(2)->endOfMonth(),
            ],
            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
            ],
            'last_year' => [
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
                $now->copy()->subYears(2)->startOfYear(),
                $now->copy()->subYears(2)->endOfYear(),
            ],
            // last_x_days: current = last N days, previous = N days before that
            'last_x_days' => [
                $now->copy()->subDays($days)->startOfDay(),
                $now->copy()->endOfDay(),
                $now->copy()->subDays($days * 2)->startOfDay(),
                $now->copy()->subDays($days)->subSecond(),
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
        };
    }

    // ── Stat builders ──────────────────────────────────────────────────────

    /**
     * Build stat for models using HasUsineScope (Prestataire, Vehicule).
     * The global scope auto-applies the usine filter when UsineContext is set.
     */
    private function buildEntityStat(
        string $modelClass,
        Carbon $from,
        Carbon $to,
        Carbon $prevFrom,
        Carbon $prevTo,
    ): array {
        $value    = $modelClass::query()->count();
        $current  = $modelClass::query()->whereBetween('created_at', [$from, $to])->count();
        $previous = $modelClass::query()->whereBetween('created_at', [$prevFrom, $prevTo])->count();

        [$deltaPct, $trend] = $this->computeDelta($current, $previous);
        $sparkline = $this->buildSparkline($modelClass, $from, $to);

        return [
            'value'     => $value,
            'delta_pct' => $deltaPct,
            'trend'     => $trend,
            'sparkline' => $sparkline,
        ];
    }

    /**
     * Build stat for users.
     * User does not use HasUsineScope; the usine filter is applied manually
     * via the user_usines pivot when a usine context is present.
     */
    private function buildUserStat(
        Carbon $from,
        Carbon $to,
        Carbon $prevFrom,
        Carbon $prevTo,
        ?int $usineId,
    ): array {
        $base = fn () => User::query()
            ->where('type', 'staff')
            ->when($usineId, fn ($q) => $q->whereHas(
                'usines',
                fn ($iq) => $iq->where('usines.id', $usineId)
            ));

        $value    = $base()->count();
        $current  = $base()->whereBetween('created_at', [$from, $to])->count();
        $previous = $base()->whereBetween('created_at', [$prevFrom, $prevTo])->count();

        [$deltaPct, $trend] = $this->computeDelta($current, $previous);
        $sparkline = $this->buildUserSparkline($from, $to, $usineId);

        return [
            'value'     => $value,
            'delta_pct' => $deltaPct,
            'trend'     => $trend,
            'sparkline' => $sparkline,
        ];
    }

    /**
     * Build stat for rouleaux en stock.
     *
     * value    = current stock quantity for the configured rouleau product.
     * delta    = packing activity this period vs previous period (proxy for
     *            stock consumption trend; more packings = higher activity).
     * sparkline = daily packing counts over the period.
     *
     * Note: true stock history is not persisted, so we use packings as a proxy.
     * If no rouleau product is configured, all fields return safe zero values.
     */
    private function buildRouleauxStat(
        Carbon $from,
        Carbon $to,
        Carbon $prevFrom,
        Carbon $prevTo,
        ?int $usineId,
    ): array {
        $produitRouleauId = Parametre::getProduitRouleauId();

        if (! $produitRouleauId) {
            return [
                'value'     => 0,
                'delta_pct' => null,
                'trend'     => 'flat',
                'sparkline' => array_fill(0, 7, 0),
            ];
        }

        // Current stock value (explicit usine filter — Stock has no HasUsineScope)
        $stockQuery = Stock::query()->where('produit_id', $produitRouleauId);
        if ($usineId) {
            $stockQuery->where('usine_id', $usineId);
        }
        $value = (int) $stockQuery->sum('qte_stock');

        // Use packings as proxy for consumption/activity trend
        // HasUsineScope auto-filters Packing when context is set
        $current  = Packing::query()->whereBetween('created_at', [$from, $to])->count();
        $previous = Packing::query()->whereBetween('created_at', [$prevFrom, $prevTo])->count();

        [$deltaPct, $trend] = $this->computeDelta($current, $previous);
        $sparkline = $this->buildSparkline(Packing::class, $from, $to);

        return [
            'value'     => $value,
            'delta_pct' => $deltaPct,
            'trend'     => $trend,
            'sparkline' => $sparkline,
        ];
    }

    // ── Delta computation ──────────────────────────────────────────────────

    /**
     * Compute percentage delta and trend label.
     *
     * Returns [?float $deltaPct, string $trend].
     * When previous = 0, deltaPct is null (indeterminate) and trend is 'flat'.
     */
    private function computeDelta(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [null, 'flat'];
        }

        $deltaPct = round(($current - $previous) / $previous * 100, 1);
        $trend    = match (true) {
            $deltaPct > 0  => 'up',
            $deltaPct < 0  => 'down',
            default        => 'flat',
        };

        return [$deltaPct, $trend];
    }

    // ── Sparkline builders ─────────────────────────────────────────────────

    /**
     * Build a 7-point sparkline for HasUsineScope models.
     * Splits the period into 7 equal time buckets and counts records per bucket.
     */
    private function buildSparkline(string $modelClass, Carbon $from, Carbon $to): array
    {
        $totalSeconds = max(1, $to->timestamp - $from->timestamp);
        $bucketSize   = intdiv($totalSeconds, 7);
        $result       = [];

        for ($i = 0; $i < 7; $i++) {
            $bucketFrom = $from->copy()->addSeconds($bucketSize * $i);
            $bucketTo   = $i < 6
                ? $from->copy()->addSeconds($bucketSize * ($i + 1) - 1)
                : $to->copy();

            $result[] = $modelClass::query()
                ->whereBetween('created_at', [$bucketFrom, $bucketTo])
                ->count();
        }

        return $result;
    }

    /**
     * Build a 7-point sparkline for User (manual usine filter).
     */
    private function buildUserSparkline(Carbon $from, Carbon $to, ?int $usineId): array
    {
        $totalSeconds = max(1, $to->timestamp - $from->timestamp);
        $bucketSize   = intdiv($totalSeconds, 7);
        $result       = [];

        for ($i = 0; $i < 7; $i++) {
            $bucketFrom = $from->copy()->addSeconds($bucketSize * $i);
            $bucketTo   = $i < 6
                ? $from->copy()->addSeconds($bucketSize * ($i + 1) - 1)
                : $to->copy();

            $result[] = User::query()
                ->where('type', 'staff')
                ->when($usineId, fn ($q) => $q->whereHas(
                    'usines',
                    fn ($iq) => $iq->where('usines.id', $usineId)
                ))
                ->whereBetween('created_at', [$bucketFrom, $bucketTo])
                ->count();
        }

        return $result;
    }

    // ── Vehicules par type ─────────────────────────────────────────────────

    /**
     * Count active vehicles grouped by type_vehicule (HasUsineScope auto-applied).
     * Returns an array ordered by count DESC, including only types with at least one vehicle.
     *
     * @return array<int, array{type: string, label: string, count: int}>
     */
    private function buildVehiculesByType(): array
    {
        // Label map for all known types
        $labels = [
            TypeVehicule::CAMION->value   => 'Camions',
            TypeVehicule::VANNE->value    => 'Vannes',
            TypeVehicule::TRICYCLE->value => 'Tri-cycles',
            TypeVehicule::MOTO->value     => 'Motos',
            TypeVehicule::PICK_UP->value  => 'Pick-up',
            TypeVehicule::AUTRE->value    => 'Autre',
        ];

        return Vehicule::query()
            ->selectRaw('type_vehicule, COUNT(*) as total')
            ->groupBy('type_vehicule')
            ->orderByRaw('COUNT(*) DESC')
            ->get()
            ->map(fn ($row) => [
                'type'  => $row->type_vehicule instanceof TypeVehicule
                    ? $row->type_vehicule->value
                    : (string) $row->type_vehicule,
                'label' => $labels[$row->type_vehicule instanceof TypeVehicule
                    ? $row->type_vehicule->value
                    : (string) $row->type_vehicule] ?? ucfirst((string) $row->type_vehicule),
                'count' => (int) $row->total,
            ])
            ->values()
            ->toArray();
    }
}
