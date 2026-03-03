<?php

namespace App\Http\Controllers\Packing;

use App\Enums\PackingStatut;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackingStatsController extends Controller
{
    use ApiResponse;

    private const PERIODS = [
        'today', 'yesterday',
        'this_week', 'last_week',
        'this_month', 'last_month',
        'q1', 'q2', 'q3', 'q4',
        's1', 's2',
        'this_year', 'last_year',
    ];

    public function __invoke(Request $request)
    {
        try {
            $validated = $request->validate([
                'period' => ['nullable', Rule::in(self::PERIODS)],
            ], [
                'period.in' => 'Période invalide.',
            ]);

            $period = $validated['period'] ?? 'this_week';

            [$start, $end, $labels, $groupBy] = $this->resolvePeriod($period);

            $stats = $this->buildStats($start, $end, $groupBy, count($labels), $start->month);

            return $this->successResponse([
                'period'    => $period,
                'labels'    => $labels,
                'payee'     => $stats['payee'],
                'impayee'   => $stats['impayee'],
                'partielle' => $stats['partielle'],
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors du calcul des statistiques.', $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Résolution de la période → [start, end, labels, groupBy]
    // ──────────────────────────────────────────────────────────────────────

    private function resolvePeriod(string $period): array
    {
        $now = now();
        $y   = $now->year;

        return match ($period) {

            // Horaire (groupé par tranches de 4h sur created_at)
            'today' => [
                $now->copy()->startOfDay(), $now->copy()->endOfDay(),
                ['00h', '04h', '08h', '12h', '16h', '20h'],
                'hour4',
            ],
            'yesterday' => [
                $now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(),
                ['00h', '04h', '08h', '12h', '16h', '20h'],
                'hour4',
            ],

            // Journalier Lun→Dim (sur champ `date`)
            'this_week' => [
                $now->copy()->startOfWeek(Carbon::MONDAY),
                $now->copy()->endOfWeek(Carbon::SUNDAY),
                ['LUN', 'MAR', 'MER', 'JEU', 'VEN', 'SAM', 'DIM'],
                'weekday',
            ],
            'last_week' => [
                $now->copy()->subWeek()->startOfWeek(Carbon::MONDAY),
                $now->copy()->subWeek()->endOfWeek(Carbon::SUNDAY),
                ['LUN', 'MAR', 'MER', 'JEU', 'VEN', 'SAM', 'DIM'],
                'weekday',
            ],

            // Semaines du mois (Sem 1 … Sem N)
            'this_month' => [
                $s = $now->copy()->startOfMonth(), $e = $now->copy()->endOfMonth(),
                $this->weekLabels($s), 'week_of_month',
            ],
            'last_month' => [
                $s = $now->copy()->subMonth()->startOfMonth(), $e = $now->copy()->subMonth()->endOfMonth(),
                $this->weekLabels($s), 'week_of_month',
            ],

            // Trimestriels (3 mois chacun)
            'q1' => [Carbon::create($y,1,1), Carbon::create($y,3,31)->endOfDay(), ['JAN','FÉV','MAR'], 'month'],
            'q2' => [Carbon::create($y,4,1), Carbon::create($y,6,30)->endOfDay(), ['AVR','MAI','JUN'], 'month'],
            'q3' => [Carbon::create($y,7,1), Carbon::create($y,9,30)->endOfDay(), ['JUL','AOÛ','SEP'], 'month'],
            'q4' => [Carbon::create($y,10,1),Carbon::create($y,12,31)->endOfDay(),['OCT','NOV','DÉC'], 'month'],

            // Semestriels
            's1' => [Carbon::create($y,1,1), Carbon::create($y,6,30)->endOfDay(), ['JAN','FÉV','MAR','AVR','MAI','JUN'], 'month'],
            's2' => [Carbon::create($y,7,1), Carbon::create($y,12,31)->endOfDay(),['JUL','AOÛ','SEP','OCT','NOV','DÉC'],'month'],

            // Annuel
            'this_year' => [
                $now->copy()->startOfYear(), $now->copy()->endOfYear(),
                ['JAN','FÉV','MAR','AVR','MAI','JUN','JUL','AOÛ','SEP','OCT','NOV','DÉC'],
                'month',
            ],
            'last_year' => [
                $now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear(),
                ['JAN','FÉV','MAR','AVR','MAI','JUN','JUL','AOÛ','SEP','OCT','NOV','DÉC'],
                'month',
            ],
        };
    }

    // ──────────────────────────────────────────────────────────────────────
    // Construction des données
    // ──────────────────────────────────────────────────────────────────────

    private function buildStats(Carbon $start, Carbon $end, string $groupBy, int $slots, int $startMonth): array
    {
        $payee    = array_fill(0, $slots, 0);
        $impayee  = array_fill(0, $slots, 0);
        $partielle = array_fill(0, $slots, 0);

        if ($groupBy === 'hour4') {
            // Grouper par tranche de 4h via created_at
            Packing::whereBetween('created_at', [$start, $end])
                ->get(['created_at', 'statut'])
                ->each(function ($p) use (&$payee, &$impayee, &$partielle) {
                    $slot = (int) floor(Carbon::parse($p->created_at)->hour / 4);
                    $this->increment($p->statut, min($slot, 5), $payee, $impayee, $partielle);
                });

        } elseif ($groupBy === 'weekday') {
            Packing::whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->get(['date', 'statut'])
                ->each(function ($p) use (&$payee, &$impayee, &$partielle) {
                    $idx = Carbon::parse($p->date)->dayOfWeekIso - 1; // 0=Lun, 6=Dim
                    $this->increment($p->statut, $idx, $payee, $impayee, $partielle);
                });

        } elseif ($groupBy === 'week_of_month') {
            Packing::whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->get(['date', 'statut'])
                ->each(function ($p) use (&$payee, &$impayee, &$partielle, $slots) {
                    $day = Carbon::parse($p->date)->day;
                    $idx = min((int) floor(($day - 1) / 7), $slots - 1);
                    $this->increment($p->statut, $idx, $payee, $impayee, $partielle);
                });

        } elseif ($groupBy === 'month') {
            Packing::whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->get(['date', 'statut'])
                ->each(function ($p) use (&$payee, &$impayee, &$partielle, $startMonth) {
                    $idx = Carbon::parse($p->date)->month - $startMonth;
                    $this->increment($p->statut, $idx, $payee, $impayee, $partielle);
                });
        }

        return compact('payee', 'impayee', 'partielle');
    }

    private function increment($statut, int $idx, array &$payee, array &$impayee, array &$partielle): void
    {
        $val = $statut instanceof PackingStatut ? $statut->value : (string) $statut;

        if ($val === PackingStatut::PAYEE->value) {
            $payee[$idx]++;
        } elseif ($val === PackingStatut::IMPAYEE->value) {
            $impayee[$idx]++;
        } elseif ($val === PackingStatut::PARTIELLE->value) {
            $partielle[$idx]++;
        }
        // ANNULEE ignoré
    }

    private function weekLabels(Carbon $monthStart): array
    {
        $numWeeks = (int) ceil($monthStart->daysInMonth / 7);
        return array_map(fn ($i) => 'Sem ' . $i, range(1, $numWeeks));
    }
}
