<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\StatutFactureVente;
use App\Enums\TypeVehicule;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\SiteContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/dashboard/ventes/par-type-vehicule
 *
 * Retourne le CA (montant_brut) groupé par type de véhicule × statut de facture
 * pour la période demandée.
 *
 * Query params :
 *   - period   : today | yesterday | this_week | last_week | this_month | last_month
 *                | q1 | q2 | q3 | q4 | s1 | s2 | this_year | last_year | last_x_days
 *   - days     : nb jours (si period = last_x_days)
 *
 * Réponse :
 *  {
 *    "period"   : { "from": "...", "to": "..." },
 *    "lignes"   : [{ "type_vehicule", "label", "statut_facture", "ca_total", "nb_commandes" }],
 *    "par_type" : [{ "type_vehicule", "label", "ca_total", "nb_commandes" }]
 *  }
 */
class VenteStatTypeVehiculeController extends Controller
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

        // ── Requête principale ─────────────────────────────────────────────
        // CA groupé par type_vehicule × statut_facture
        $rows = DB::table('commandes_ventes as cv')
            ->join('vehicules as v', 'v.id', '=', 'cv.vehicule_id')
            ->join('factures_ventes as fv', 'fv.commande_vente_id', '=', 'cv.id')
            ->select(
                'v.type_vehicule',
                'fv.statut_facture',
                DB::raw('SUM(fv.montant_brut) as ca_total'),
                DB::raw('COUNT(cv.id) as nb_commandes'),
            )
            ->whereNull('cv.deleted_at')
            ->whereNull('fv.deleted_at')
            ->when($siteId, fn ($q) => $q->where('cv.site_id', $siteId))
            ->whereBetween('cv.created_at', [$from, $to])
            ->groupBy('v.type_vehicule', 'fv.statut_facture')
            ->orderBy('v.type_vehicule')
            ->orderBy('fv.statut_facture')
            ->get();

        // ── Formatage lignes ───────────────────────────────────────────────
        $lignes = $rows->map(fn ($row) => [
            'type_vehicule'  => $row->type_vehicule,
            'label'          => self::TYPE_LABELS[$row->type_vehicule] ?? ucfirst($row->type_vehicule),
            'statut_facture' => $row->statut_facture,
            'ca_total'       => round((float) $row->ca_total, 2),
            'nb_commandes'   => (int) $row->nb_commandes,
        ])->values()->toArray();

        // ── Totaux par type (agrégat des statuts) ─────────────────────────
        $parType = collect($lignes)
            ->groupBy('type_vehicule')
            ->map(function ($group, $type) {
                return [
                    'type_vehicule' => $type,
                    'label'         => $group->first()['label'],
                    'ca_total'      => round($group->sum('ca_total'), 2),
                    'nb_commandes'  => $group->sum('nb_commandes'),
                ];
            })
            ->values()
            ->toArray();

        // ── Totaux par statut de facture (tous types confondus) ────────────
        $statutOrder = ['payee', 'partiel', 'impayee', 'annulee'];

        $parStatut = collect($lignes)
            ->groupBy('statut_facture')
            ->map(function ($group, $statut) {
                return [
                    'statut_facture' => $statut,
                    'ca_total'       => round($group->sum('ca_total'), 2),
                    'nb_commandes'   => $group->sum('nb_commandes'),
                ];
            })
            ->sortBy(fn ($row) => array_search($row['statut_facture'], ['payee', 'partiel', 'impayee', 'annulee']))
            ->values()
            ->toArray();

        return $this->successResponse([
            'period'     => [
                'key'  => $period,
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'lignes'     => $lignes,
            'par_type'   => $parType,
            'par_statut' => $parStatut,
        ], 'CA par type de véhicule et statut de facture');
    }

    // ── Period resolution (même logique que DashboardStatsController) ──────

    private function resolvePeriod(string $period, ?int $days): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today'      => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
            'yesterday'  => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
            'this_week'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week'  => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'q1'         => [Carbon::create($now->year, 1, 1)->startOfDay(), Carbon::create($now->year, 3, 31)->endOfDay()],
            'q2'         => [Carbon::create($now->year, 4, 1)->startOfDay(), Carbon::create($now->year, 6, 30)->endOfDay()],
            'q3'         => [Carbon::create($now->year, 7, 1)->startOfDay(), Carbon::create($now->year, 9, 30)->endOfDay()],
            'q4'         => [Carbon::create($now->year, 10, 1)->startOfDay(), Carbon::create($now->year, 12, 31)->endOfDay()],
            's1'         => [Carbon::create($now->year, 1, 1)->startOfDay(), Carbon::create($now->year, 6, 30)->endOfDay()],
            's2'         => [Carbon::create($now->year, 7, 1)->startOfDay(), Carbon::create($now->year, 12, 31)->endOfDay()],
            'this_year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year'  => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'last_x_days' => [$now->copy()->subDays($days)->startOfDay(), $now->copy()->endOfDay()],
            default      => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }
}
