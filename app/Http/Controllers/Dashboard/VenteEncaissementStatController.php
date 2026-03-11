<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\SiteContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/dashboard/ventes/encaissements
 *
 * Metriques financieres globales des factures vente pour la periode :
 *   - total_factures    : SUM(montant_brut) toutes factures non annulees
 *   - factures_payees   : SUM(montant_brut) des factures statut = payee
 *   - reste_a_encaisser : SUM(montant_net - encaisse) des factures impayee / partiel
 *
 * Query params :
 *   - period  : today | yesterday | this_week | last_week | this_month | last_month
 *               | q1 | q2 | q3 | q4 | s1 | s2 | this_year | last_year | last_x_days
 *   - days    : requis si period = last_x_days
 */
class VenteEncaissementStatController extends Controller
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

    public function __invoke(Request $request): JsonResponse
    {
        $period = $request->get('period', 'this_month');

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
        $siteId = app(SiteContext::class)->getCurrentSiteId();

        $cacheKey = sprintf(
            'dashboard:ventes:encaissements:%s:%s:%s',
            $siteId ?? 'all',
            $period,
            $days ?? 'na'
        );

        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($from, $to, $siteId, $period) {
            // Agrégats factures en 1 seul scan
            $factureAgg = DB::table('factures_ventes as fv')
                ->selectRaw("
                    SUM(CASE WHEN fv.statut_facture != 'annulee' THEN fv.montant_brut ELSE 0 END) AS total_factures,
                    SUM(CASE WHEN fv.statut_facture != 'annulee' THEN 1 ELSE 0 END) AS nb_factures_total,
                    SUM(CASE WHEN fv.statut_facture = 'payee' THEN fv.montant_brut ELSE 0 END) AS factures_payees,
                    SUM(CASE WHEN fv.statut_facture = 'payee' THEN 1 ELSE 0 END) AS nb_factures_payees,
                    SUM(CASE WHEN fv.statut_facture IN ('impayee', 'partiel') THEN fv.montant_net ELSE 0 END) AS montant_du,
                    SUM(CASE WHEN fv.statut_facture IN ('impayee', 'partiel') THEN 1 ELSE 0 END) AS nb_factures_impayees,
                    SUM(CASE WHEN fv.statut_facture = 'annulee' THEN 1 ELSE 0 END) AS nb_factures_annulees
                ")
                ->whereNull('fv.deleted_at')
                ->when($siteId, fn ($q) => $q->where('fv.site_id', $siteId))
                ->whereBetween('fv.created_at', [$from, $to])
                ->first();

            // Encaissements lies aux factures non soldees
            $montantEncaisse = DB::table('encaissements_ventes as ev')
                ->join('factures_ventes as fv', 'fv.id', '=', 'ev.facture_vente_id')
                ->whereNull('fv.deleted_at')
                ->whereIn('fv.statut_facture', ['impayee', 'partiel'])
                ->when($siteId, fn ($q) => $q->where('fv.site_id', $siteId))
                ->whereBetween('fv.created_at', [$from, $to])
                ->sum('ev.montant');

            $montantDu = (float) ($factureAgg->montant_du ?? 0);
            $resteAEncaisser = max(0, $montantDu - (float) $montantEncaisse);

            return [
                'period' => [
                    'key'  => $period,
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                ],
                'total_factures'       => round((float) ($factureAgg->total_factures ?? 0), 2),
                'factures_payees'      => round((float) ($factureAgg->factures_payees ?? 0), 2),
                'reste_a_encaisser'    => round($resteAEncaisser, 2),
                'nb_factures_total'    => (int) ($factureAgg->nb_factures_total ?? 0),
                'nb_factures_payees'   => (int) ($factureAgg->nb_factures_payees ?? 0),
                'nb_factures_impayees' => (int) ($factureAgg->nb_factures_impayees ?? 0),
                'nb_factures_annulees' => (int) ($factureAgg->nb_factures_annulees ?? 0),
            ];
        });

        return $this->successResponse($payload, 'Statistiques d\'encaissement des factures vente');
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
            default       => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }
}

