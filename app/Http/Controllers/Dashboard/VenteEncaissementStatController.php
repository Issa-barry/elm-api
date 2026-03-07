<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\SiteContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/dashboard/ventes/encaissements
 *
 * Métriques financières globales des factures vente pour la période :
 *   - total_factures    : SUM(montant_brut) toutes factures non annulées
 *   - factures_payees   : SUM(montant_brut) des factures statut = payee
 *   - reste_a_encaisser : SUM(montant_net - encaissé) des factures impayee / partiel
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

        $siteId = app(SiteContext::class)->getCurrentSiteId();

        // ── Base : factures non supprimées de la période ───────────────────
        $base = DB::table('factures_ventes as fv')
            ->join('commandes_ventes as cv', 'cv.id', '=', 'fv.commande_vente_id')
            ->whereNull('fv.deleted_at')
            ->whereNull('cv.deleted_at')
            ->when($siteId, fn ($q) => $q->where('fv.site_id', $siteId))
            ->whereBetween('cv.created_at', [$from, $to]);

        // ── Total factures (hors annulées) ────────────────────────────────
        $totalFactures = (clone $base)
            ->where('fv.statut_facture', '!=', 'annulee')
            ->sum('fv.montant_brut');

        $nbFacturesTotal = (clone $base)
            ->where('fv.statut_facture', '!=', 'annulee')
            ->count();

        // ── Factures payées ────────────────────────────────────────────────
        $facturesPayees = (clone $base)
            ->where('fv.statut_facture', 'payee')
            ->sum('fv.montant_brut');

        $nbFacturesPayees = (clone $base)
            ->where('fv.statut_facture', 'payee')
            ->count();

        // ── Reste à encaisser (impayee + partiel) ─────────────────────────
        // = SUM(montant_net) - SUM(encaissements) pour factures non soldées
        $montantDu = (clone $base)
            ->whereIn('fv.statut_facture', ['impayee', 'partiel'])
            ->sum('fv.montant_net');

        $montantEncaisse = DB::table('encaissements_ventes as ev')
            ->join('factures_ventes as fv', 'fv.id', '=', 'ev.facture_vente_id')
            ->join('commandes_ventes as cv', 'cv.id', '=', 'fv.commande_vente_id')
            ->whereNull('fv.deleted_at')
            ->whereNull('cv.deleted_at')
            ->whereIn('fv.statut_facture', ['impayee', 'partiel'])
            ->when($siteId, fn ($q) => $q->where('fv.site_id', $siteId))
            ->whereBetween('cv.created_at', [$from, $to])
            ->sum('ev.montant');

        $resteAEncaisser = max(0, (float) $montantDu - (float) $montantEncaisse);

        $nbFacturesImpayees = (clone $base)
            ->whereIn('fv.statut_facture', ['impayee', 'partiel'])
            ->count();

        // ── Factures annulées (info) ───────────────────────────────────────
        $nbFacturesAnnulees = (clone $base)
            ->where('fv.statut_facture', 'annulee')
            ->count();

        return $this->successResponse([
            'period' => [
                'key'  => $period,
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'total_factures'      => round((float) $totalFactures, 2),
            'factures_payees'     => round((float) $facturesPayees, 2),
            'reste_a_encaisser'   => round($resteAEncaisser, 2),
            'nb_factures_total'   => (int) $nbFacturesTotal,
            'nb_factures_payees'  => (int) $nbFacturesPayees,
            'nb_factures_impayees'=> (int) $nbFacturesImpayees,
            'nb_factures_annulees'=> (int) $nbFacturesAnnulees,
        ], 'Statistiques d\'encaissement des factures vente');
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
