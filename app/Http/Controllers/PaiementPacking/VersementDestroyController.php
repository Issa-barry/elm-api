<?php

namespace App\Http\Controllers\PaiementPacking;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Versement;

class VersementDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(int $paiementId, int $versementId)
    {
        try {
            $versement = Versement::where('paiement_packing_id', $paiementId)
                ->findOrFail($versementId);

            $versement->delete();

            return $this->successResponse(null, 'Versement supprimé avec succès');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Versement non trouvé');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la suppression du versement', $e->getMessage());
        }
    }
}
