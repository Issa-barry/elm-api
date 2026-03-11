<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\SiteContext;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/dashboard/ventes/evolution-par-type
 *
 * Retourne la serie temporelle du CA (montant_brut) par type de vehicule.
 */
class VenteEvolutionTypeVehiculeController extends Controller
{
    use ApiResponse;

    private const ALLOWED_PERIODS = [
        'today', 'yesterday',
        'this_week', 'last_week',
        'this_month', 'last_month',
        'q1', 'q2', 'q3', 'q4',
        's1', 's2',
        'this_year', 'last_year',
        'last_x_days',
    ];

    private const TYPE_LABELS = [
        'camion'   => 'Camion',
        'tricycle' => 'Tricycle',
        'vanne'    => 'Vanne',
    ];

    private const MOIS_FR = [
        1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Avr',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'Aout',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $period = $request->get('period', 'this_year');

        if (! in_array($period, self::ALLOWED_PERIODS, true)) {
            return $this->errorResponse(
                'Periode invalide. Valeurs acceptees : ' . implode(', ', self::ALLOWED_PERIODS),
                null,
                422
            );
        }

        $days = null;
        if ($period === 'last_x_days') {
            $days = (int) $request->get('days', 30);
            if ($days < 1) {
                return $this->errorResponse('Le parametre days doit etre un entier > 0.', null, 422);
            }
        }

        [$from, $to] = $this->resolvePeriod($period, $days);
        $nbJours = (int) $from->diffInDays($to) + 1;
        $granularity = $this->resolveGranularity($nbJours);
        $siteId = app(SiteContext::class)->getCurrentSiteId();

        $cacheKey = sprintf(
            'dashboard:ventes:evolution-type:%s:%s:%s',
            $siteId ?? 'all',
            $period,
            $days ?? 'na'
        );

        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () use (
            $from,
            $to,
            $siteId,
            $period,
            $granularity
        ) {
            $buckets = $this->buildBuckets($from, $to, $granularity);

            $formatSQL = match ($granularity) {
                'day'   => "DATE_FORMAT(fv.created_at, '%Y-%m-%d')",
                'week'  => "DATE_FORMAT(fv.created_at, '%x-W%v')",
                default => "DATE_FORMAT(fv.created_at, '%Y-%m')",
            };

            $rows = DB::table('factures_ventes as fv')
                ->join('vehicules as v', 'v.id', '=', 'fv.vehicule_id')
                ->selectRaw("v.type_vehicule, {$formatSQL} as bucket, SUM(fv.montant_brut) as ca_total")
                ->whereNull('fv.deleted_at')
                ->where('fv.statut_facture', '!=', 'annulee')
                ->when($siteId, fn ($q) => $q->where('fv.site_id', $siteId))
                ->whereBetween('fv.created_at', [$from, $to])
                ->groupByRaw("v.type_vehicule, {$formatSQL}")
                ->orderBy('bucket')
                ->get();

            $index = [];
            foreach ($rows as $row) {
                $index[$row->type_vehicule][$row->bucket] = (float) $row->ca_total;
            }

            $datasets = [];
            foreach (self::TYPE_LABELS as $type => $label) {
                $data = [];
                foreach ($buckets as $bucket) {
                    $data[] = round($index[$type][$bucket['key']] ?? 0, 2);
                }

                $datasets[] = [
                    'type_vehicule' => $type,
                    'label'         => $label,
                    'data'          => $data,
                ];
            }

            return [
                'period' => [
                    'key'         => $period,
                    'from'        => $from->toDateString(),
                    'to'          => $to->toDateString(),
                    'granularity' => $granularity,
                ],
                'labels'   => array_column($buckets, 'label'),
                'datasets' => $datasets,
            ];
        });

        return $this->successResponse($payload, 'Evolution CA par type de vehicule');
    }

    private function resolveGranularity(int $nbJours): string
    {
        if ($nbJours <= 14) {
            return 'day';
        }
        if ($nbJours <= 90) {
            return 'week';
        }

        return 'month';
    }

    private function buildBuckets(Carbon $from, Carbon $to, string $granularity): array
    {
        $buckets = [];

        match ($granularity) {
            'day' => (function () use ($from, $to, &$buckets) {
                foreach (CarbonPeriod::create($from, '1 day', $to) as $date) {
                    $buckets[] = [
                        'key'   => $date->format('Y-m-d'),
                        'label' => $date->format('d/m'),
                    ];
                }
            })(),

            'week' => (function () use ($from, $to, &$buckets) {
                $cursor = $from->copy()->startOfWeek();
                while ($cursor->lte($to)) {
                    $buckets[] = [
                        'key'   => $cursor->format('o') . '-W' . $cursor->format('W'),
                        'label' => 'S' . $cursor->format('W') . ' ' . $cursor->format('Y'),
                    ];
                    $cursor->addWeek();
                }
            })(),

            default => (function () use ($from, $to, &$buckets) {
                $cursor = $from->copy()->startOfMonth();
                while ($cursor->lte($to)) {
                    $buckets[] = [
                        'key'   => $cursor->format('Y-m'),
                        'label' => (self::MOIS_FR[$cursor->month] ?? $cursor->format('M')) . ' ' . $cursor->format('Y'),
                    ];
                    $cursor->addMonth();
                }
            })(),
        };

        return $buckets;
    }

    private function resolvePeriod(string $period, ?int $days): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today'       => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
            'yesterday'   => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
            'this_week'   => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week'   => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month'  => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month'  => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'q1'          => [Carbon::create($now->year, 1, 1)->startOfDay(), Carbon::create($now->year, 3, 31)->endOfDay()],
            'q2'          => [Carbon::create($now->year, 4, 1)->startOfDay(), Carbon::create($now->year, 6, 30)->endOfDay()],
            'q3'          => [Carbon::create($now->year, 7, 1)->startOfDay(), Carbon::create($now->year, 9, 30)->endOfDay()],
            'q4'          => [Carbon::create($now->year, 10, 1)->startOfDay(), Carbon::create($now->year, 12, 31)->endOfDay()],
            's1'          => [Carbon::create($now->year, 1, 1)->startOfDay(), Carbon::create($now->year, 6, 30)->endOfDay()],
            's2'          => [Carbon::create($now->year, 7, 1)->startOfDay(), Carbon::create($now->year, 12, 31)->endOfDay()],
            'this_year'   => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year'   => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'last_x_days' => [$now->copy()->subDays($days)->startOfDay(), $now->copy()->endOfDay()],
            default       => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
        };
    }
}

