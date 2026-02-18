<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Prestataire;

class PrestataireShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            $prestataire = Prestataire::find($id);

            if (!$prestataire) {
                return $this->notFoundResponse('Prestataire non trouvé');
            }

            return $this->successResponse($prestataire, 'Prestataire récupéré avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération du prestataire', $e->getMessage());
        }
    }
}
 