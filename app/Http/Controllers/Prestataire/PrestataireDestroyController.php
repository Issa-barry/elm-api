<?php

namespace App\Http\Controllers\Prestataire;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
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

            $hasActivePacking = Packing::where('prestataire_id', $prestataire->id)
                ->whereIn('statut', [Packing::STATUT_IMPAYEE, Packing::STATUT_PARTIELLE])
                ->exists();

            if ($hasActivePacking) {
                return $this->errorResponse(
                    'Ce prestataire a des packings en cours (impayés ou partiels). Vous pouvez l\'archiver une fois tous ses packings payés ou annulés.',
                    null,
                    422
                );
            }

            $prestataire->delete();

            return $this->successResponse(null, 'Prestataire supprimé avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la suppression du prestataire', $e->getMessage());
        }
    }
}
 