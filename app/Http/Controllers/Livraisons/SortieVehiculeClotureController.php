<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\SortieVehicule;
use Illuminate\Http\Request;

class SortieVehiculeClotureController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id)
    {
        $sortie = SortieVehicule::find($id);

        if (!$sortie) {
            return $this->notFoundResponse('Sortie véhicule non trouvée');
        }

        if (!$sortie->isRetourne()) {
            return $this->errorResponse('Seule une sortie avec retour enregistré peut être clôturée.', null, 422);
        }

        $sortie->update(['statut_sortie' => 'cloture']);

        return $this->successResponse($sortie->fresh(), 'Sortie clôturée avec succès');
    }
}
