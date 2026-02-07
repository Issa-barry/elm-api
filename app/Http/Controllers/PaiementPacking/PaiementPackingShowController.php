<?php

namespace App\Http\Controllers\PaiementPacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PaiementPacking;

class PaiementPackingShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            $paiement = PaiementPacking::with([
                'prestataire',
                'packings',
                'versements.creator',
                'creator',
                'validator',
            ])->findOrFail($id);

            return $this->successResponse($paiement, 'Paiement récupéré avec succès');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Paiement non trouvé');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération du paiement', $e->getMessage());
        }
    }
}
