<?php

namespace App\Http\Controllers\FacturePacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use App\Models\Parametre;
use Illuminate\Http\Request;

class FacturePackingPreviewController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            // Validation de base
            $request->validate([
                'prestataire_id' => 'required|exists:prestataires,id',
                'periode' => 'nullable|integer|in:1,2',
                'mois' => 'nullable|integer|min:1|max:12',
                'annee' => 'nullable|integer|min:2020|max:2100',
                'periode_debut' => 'required_without:periode|date',
                'periode_fin' => 'required_without:periode|date|after_or_equal:periode_debut',
            ]);

            // Si une période est spécifiée, calculer les dates automatiquement
            if ($request->has('periode')) {
                $mois = $request->integer('mois', (int) now()->format('m'));
                $annee = $request->integer('annee', (int) now()->format('Y'));
                $dates = Parametre::getPeriodeDates($request->integer('periode'), $mois, $annee);

                $request->merge([
                    'periode_debut' => $dates['debut'],
                    'periode_fin' => $dates['fin'],
                ]);
            }

            // Récupérer les packings facturables pour ce prestataire et cette période
            $packings = Packing::with('prestataire')
                ->where('prestataire_id', $request->prestataire_id)
                ->facturables()
                ->where('date_fin', '>=', $request->periode_debut)
                ->where('date_fin', '<=', $request->periode_fin)
                ->orderBy('date_debut')
                ->get();

            $summary = [
                'prestataire_id' => $request->integer('prestataire_id'),
                'periode_debut' => $request->periode_debut,
                'periode_fin' => $request->periode_fin,
                'nb_packings' => $packings->count(),
                'montant_total' => $packings->sum('montant'),
                'total_rouleaux' => $packings->sum('nb_rouleaux'),
                'packings' => $packings,
            ];

            return $this->successResponse($summary, 'Prévisualisation générée avec succès');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies sont invalides.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la prévisualisation', $e->getMessage());
        }
    }
}
