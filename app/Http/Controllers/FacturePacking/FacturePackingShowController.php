<?php

namespace App\Http\Controllers\FacturePacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FacturePacking;

class FacturePackingShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            $facture = FacturePacking::with([
                'prestataire',
                'packings',
                'versements.creator',
                'creator',
                'validator',
            ])->findOrFail($id);

            return $this->successResponse($facture, 'Facture récupérée avec succès');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Facture non trouvée');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération de la facture', $e->getMessage());
        }
    }
}
