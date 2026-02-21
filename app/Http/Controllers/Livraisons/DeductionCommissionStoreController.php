<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Livraison\StoreDeductionCommissionRequest;
use App\Http\Traits\ApiResponse;
use App\Models\DeductionCommission;
use App\Models\SortieVehicule;

class DeductionCommissionStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreDeductionCommissionRequest $request)
    {
        $sortie = SortieVehicule::find($request->validated('sortie_vehicule_id'));

        if (!$sortie) {
            return $this->notFoundResponse('Sortie véhicule non trouvée');
        }

        if ($sortie->isCloture() && $sortie->paiementCommission()->exists()) {
            return $this->errorResponse('Impossible d\'ajouter une déduction après le paiement de la commission.', null, 422);
        }

        $deduction = DeductionCommission::create($request->validated());

        return $this->createdResponse($deduction, 'Déduction enregistrée');
    }
}
