<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Prestataire;

class PrestataireDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            $prestataire = Prestataire::find($id);

            if (!$prestataire) {
                return $this->notFoundResponse('Prestataire non trouvé');
            }

            $prestataire->delete();

            return $this->successResponse(null, 'Prestataire supprimé avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la suppression du prestataire', $e->getMessage());
        }
    }
}
