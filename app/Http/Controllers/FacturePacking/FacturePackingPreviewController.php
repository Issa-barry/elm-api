<?php

namespace App\Http\Controllers\FacturePacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use Illuminate\Http\Request;

class FacturePackingPreviewController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            $request->validate([
                'prestataire_id' => 'required|exists:prestataires,id',
                'date_debut' => 'required|date',
                'date_fin' => 'required|date|after_or_equal:date_debut',
            ]);

            // Récupérer les packings facturables pour ce prestataire et cette plage de dates
            $packings = Packing::with('prestataire')
                ->where('prestataire_id', $request->prestataire_id)
                ->facturables()
                ->whereBetween('date', [$request->date_debut, $request->date_fin])
                ->orderBy('date')
                ->get();

            $summary = [
                'prestataire_id' => $request->integer('prestataire_id'),
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin,
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
