<?php

namespace App\Http\Controllers\Packing;

use App\Enums\PackingStatut;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PackingReportController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            $validated = $request->validate([
                'date_from'      => ['nullable', 'date'],
                'date_to'        => ['nullable', 'date', 'after_or_equal:date_from'],
                'prestataire_id' => ['nullable', 'integer'],
                'statut'         => ['nullable', Rule::enum(PackingStatut::class)],
                'format'         => ['nullable', Rule::in(['json', 'pdf'])],
            ], [
                'date_to.after_or_equal' => 'La date de fin doit être égale ou postérieure à la date de début.',
                'statut.enum'            => 'Statut invalide. Valeurs : impayee, partielle, payee, annulee.',
                'format.in'              => 'Format invalide. Valeurs : json, pdf.',
            ]);

            $packings = Packing::with(['prestataire', 'versements'])
                ->when($validated['date_from'] ?? null, fn ($q, $v) => $q->whereDate('date', '>=', $v))
                ->when($validated['date_to'] ?? null, fn ($q, $v) => $q->whereDate('date', '<=', $v))
                ->when($validated['prestataire_id'] ?? null, fn ($q, $v) => $q->where('prestataire_id', $v))
                ->when($validated['statut'] ?? null, fn ($q, $v) => $q->where('statut', $v))
                ->orderBy('date', 'desc')
                ->get();

            $summary = $this->buildSummary($packings);

            if (($validated['format'] ?? 'json') === 'pdf') {
                $pdf = Pdf::loadView('pdf.packings.report', [
                    'packings' => $packings,
                    'summary'  => $summary,
                    'filters'  => $validated,
                ])->setPaper('a4', 'landscape');

                return $pdf->download('rapport-packings-' . now()->format('Y-m-d') . '.pdf');
            }

            return $this->successResponse([
                'filters'  => $validated,
                'summary'  => $summary,
                'packings' => $packings->map(fn ($p) => $this->formatPacking($p)),
            ]);

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Filtres invalides.');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la génération du rapport.', $e->getMessage());
        }
    }

    private function buildSummary($packings): array
    {
        return [
            'total_packings' => $packings->count(),
            'total_rouleaux' => $packings->sum('nb_rouleaux'),
            'total_montant'  => $packings->sum('montant'),
            'total_verse'    => $packings->sum('montant_verse'),
            'total_restant'  => $packings->sum('montant_restant'),
        ];
    }

    private function formatPacking(Packing $p): array
    {
        return [
            'id'              => $p->id,
            'reference'       => $p->reference,
            'date'            => $p->date,
            'nb_rouleaux'     => $p->nb_rouleaux,
            'montant'         => $p->montant,
            'montant_verse'   => $p->montant_verse,
            'montant_restant' => $p->montant_restant,
            'statut'          => $p->statut instanceof PackingStatut ? $p->statut->value : $p->statut,
            'statut_label'    => $p->statut_label,
            'prestataire'     => $p->prestataire ? [
                'id'         => $p->prestataire->id,
                'nom_complet'=> $p->prestataire->nom_complet,
                'phone'      => $p->prestataire->phone,
            ] : null,
            'created_at'      => $p->created_at,
        ];
    }
}
