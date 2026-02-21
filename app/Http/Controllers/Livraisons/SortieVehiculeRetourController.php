<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Livraison\RetourSortieVehiculeRequest;
use App\Http\Traits\ApiResponse;
use App\Models\SortieVehicule;

class SortieVehiculeRetourController extends Controller
{
    use ApiResponse;

    public function __invoke(RetourSortieVehiculeRequest $request, int $id)
    {
        $sortie = SortieVehicule::find($id);

        if (!$sortie) {
            return $this->notFoundResponse('Sortie véhicule non trouvée');
        }

        if (!$sortie->isEnCours()) {
            return $this->errorResponse('Seule une sortie en cours peut enregistrer un retour.', null, 422);
        }

        $packsRetour = $request->validated('packs_retour');

        if ($packsRetour > $sortie->packs_charges) {
            return $this->validationErrorResponse(
                ['packs_retour' => ["Les packs retournés ({$packsRetour}) ne peuvent pas dépasser les packs chargés ({$sortie->packs_charges})."]],
                'Données invalides'
            );
        }

        $sortie->update([
            'packs_retour'  => $packsRetour,
            'date_retour'   => $request->input('date_retour', now()),
            'statut_sortie' => 'retourne',
        ]);

        return $this->successResponse($sortie->fresh(['vehicule', 'livreurEffectif']), 'Retour enregistré avec succès');
    }
}
