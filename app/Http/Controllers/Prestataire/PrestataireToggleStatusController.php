<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Prestataire;

class PrestataireToggleStatusController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        try {
            $prestataire = Prestataire::find($id);

            if (!$prestataire) {
                return $this->notFoundResponse('Prestataire non trouvé');
            }

            $prestataire->update(['is_active' => !$prestataire->is_active]);

            $status = $prestataire->is_active ? 'activé' : 'désactivé';

            return $this->successResponse($prestataire->fresh(), "Prestataire {$status} avec succès");
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors du changement de statut', $e->getMessage());
        }
    }
}
