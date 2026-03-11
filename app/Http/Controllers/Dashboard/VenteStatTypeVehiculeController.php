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
 * GET /api/v1/dashboard/ventes/par-type-vehicule
 *
 * Retourne le CA (montant_brut) groupe par type de vehicule x statut de facture.
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
            'dashboard:ventes:par-type:%s:%s:%s',
            $siteId ?? 'all',
            $period,
            $days ?? 'na'
        );

        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($from, $to, $siteId, $period) {
            $rows = DB::table('factures_ventes as fv')
                ->join('vehicules as v', 'v.id', '=', 'fv.vehicule_id')
                ->select(
                    'v.type_vehicule',
                    'fv.statut_facture',
                    DB::raw('SUM(fv.montant_brut) as ca_total'),
                    DB::raw('COUNT(fv.id) as nb_commandes'),
                )
                ->whereNull('fv.deleted_at')
                ->when($siteId, fn ($q) => $q->where('fv.site_id', $siteId))
                ->whereBetween('fv.created_at', [$from, $to])
                ->groupBy('v.type_vehicule', 'fv.statut_facture')
                ->orderBy('v.type_vehicule')
                ->orderBy('fv.statut_facture')
                ->get();

            $lignes = $rows->map(fn ($row) => [
                'type_vehicule'  => $row->type_vehicule,
                'label'          => self::TYPE_LABELS[$row->type_vehicule] ?? ucfirst((string) $row->type_vehicule),
                'statut_facture' => $row->statut_facture,
                'ca_total'       => round((float) $row->ca_total, 2),
                'nb_commandes'   => (int) $row->nb_commandes,
            ])->values()->toArray();

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

            $parStatut = collect($lignes)
                ->groupBy('statut_facture')
                ->map(function ($group, $statut) {
                    return [
                        'statut_facture' => $statut,
                        'ca_total'       => round($group->sum('ca_total'), 2),
                        'nb_commandes'   => $group->sum('nb_commandes'),
                    ];
                })
                ->sortBy(fn ($row) => array_search($row['statut_facture'], ['payee', 'partiel', 'impayee', 'annulee'], true))
                ->values()
                ->toArray();

            return [
                'period'     => [
                    'key'  => $period,
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                ],
                'lignes'     => $lignes,
                'par_type'   => $parType,
                'par_statut' => $parStatut,
            ];
        });

        return $this->successResponse($payload, 'CA par type de vehicule et statut de facture');
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

