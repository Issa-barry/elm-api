<?php

namespace App\Http\Controllers\FacturePacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FacturePacking;

class VersementIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(int $factureId)
    {
        try {
            $facture = FacturePacking::with('versements.creator')->findOrFail($factureId);

            return $this->successResponse([
                'facture_id' => $facture->id,
                'reference' => $facture->reference,
                'montant_total' => $facture->montant_total,
                'montant_verse' => $facture->montant_verse,
                'montant_restant' => $facture->montant_restant,
                'is_soldee' => $facture->montant_restant <= 0,
                'versements' => $facture->versements,
            ], 'Versements récupérés avec succès');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Facture non trouvée');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des versements', $e->getMessage());
        }
    }
}
