<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\SiteContext;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/dashboard/ventes/evolution-par-statut
 *
 * Retourne la série temporelle du CA facturé, ventilé en deux séries :
 *   - payee    : statut_facture = 'payee'
 *   - impayee  : statut_facture IN ('impayee', 'partiel')
 *
 * Les factures annulées sont exclues.
 *
 * Granularité automatique selon la période :
 *   ≤ 14 jours  → journalier
 *   ≤ 90 jours  → hebdomadaire
 *   > 90 jours  → mensuel
 *
 * Réponse :
 *  {
 *    "period"   : { "key", "from", "to", "granularity" },
 *    "labels"   : ["Jan 2026", "Fév 2026", ...],
 *    "datasets" : [
 *      { "statut": "payee",   "label": "Payées",     "data": [...] },
 *      { "statut": "partiel", "label": "Partielles", "data": [...] },
 *      { "statut": "impayee", "label": "Impayées",   "data": [...] }
 *    ]
 *  }
 */
class VenteEvolutionStatutFactureController extends Controller
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

    private const MOIS_FR = [
        1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'Août',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $period = $request->get('period', 'this_year');

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

        [$from, $to] = $this->resolvePeriod($period, $days);

        $nbJours     = (int) $from->diffInDays($to) + 1;
        $granularity = $this->resolveGranularity($nbJours);

        $siteId = app(SiteContext::class)->getCurrentSiteId();

        // ── Buckets de temps (labels) ──────────────────────────────────────
        $buckets = $this->buildBuckets($from, $to, $granularity);

        // ── Requête groupée ────────────────────────────────────────────────
        $formatSQL = match ($granularity) {
            'day'   => "DATE_FORMAT(cv.created_at, '%Y-%m-%d')",
            'week'  => "DATE_FORMAT(cv.created_at, '%x-W%v')",
            default => "DATE_FORMAT(cv.created_at, '%Y-%m')",
        };

        $rows = DB::table('commandes_ventes as cv')
            ->join('factures_ventes as fv', 'fv.commande_vente_id', '=', 'cv.id')
            ->selectRaw("fv.statut_facture as statut_groupe, {$formatSQL} as bucket, SUM(fv.montant_brut) as ca_total")
            ->whereNull('cv.deleted_at')
            ->whereNull('fv.deleted_at')
            ->whereIn('fv.statut_facture', ['payee', 'impayee', 'partiel'])
            ->when($siteId, fn ($q) => $q->where('cv.site_id', $siteId))
            ->whereBetween('cv.created_at', [$from, $to])
            ->groupByRaw("fv.statut_facture, {$formatSQL}")
            ->orderBy('bucket')
            ->get();

        // ── Indexer : [statut][bucket] = ca_total ─────────────────────────
        $index = ['payee' => [], 'impayee' => [], 'partiel' => []];
        foreach ($rows as $row) {
            $index[$row->statut_groupe][$row->bucket] = (float) $row->ca_total;
        }

        // ── Construire les trois datasets ──────────────────────────────────
        $buildData = function (string $statut) use ($buckets, $index): array {
            $data = [];
            foreach ($buckets as $bucket) {
                $data[] = round($index[$statut][$bucket['key']] ?? 0, 2);
            }

            return $data;
        };

        $datasets = [
            [
                'statut' => 'payee',
                'label'  => 'Payées',
                'data'   => $buildData('payee'),
            ],
            [
                'statut' => 'partiel',
                'label'  => 'Partielles',
                'data'   => $buildData('partiel'),
            ],
            [
                'statut' => 'impayee',
                'label'  => 'Impayées',
                'data'   => $buildData('impayee'),
            ],
        ];

        return $this->successResponse([
            'period' => [
                'key'         => $period,
                'from'        => $from->toDateString(),
                'to'          => $to->toDateString(),
                'granularity' => $granularity,
            ],
            'labels'   => array_column($buckets, 'label'),
            'datasets' => $datasets,
        ], 'Évolution CA payé vs impayé');
    }

    // ── Granularité auto ───────────────────────────────────────────────────

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

    // ── Buckets ────────────────────────────────────────────────────────────

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

    // ── Period resolution ──────────────────────────────────────────────────

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
