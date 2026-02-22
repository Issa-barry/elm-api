<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Livraison\StoreDeductionFactureRequest;
use App\Http\Traits\ApiResponse;
use App\Models\DeductionCommission;
use App\Models\FactureLivraison;

/**
 * Ajout d'une déduction de commission sur une facture de livraison (workflow simplifié).
 * Bloqué si un paiement de commission existe déjà pour cette facture.
 */
class DeductionFactureStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreDeductionFactureRequest $request, int $factureId)
    {
        $facture = FactureLivraison::with('paiementCommission')->find($factureId);

        if (!$facture) {
            return $this->notFoundResponse('Facture non trouvée');
        }

        if ($facture->paiementCommission) {
            return $this->errorResponse(
                'Impossible d\'ajouter une déduction : la commission a déjà été payée pour cette facture.',
                null,
                409
            );
        }

        $deduction = DeductionCommission::create([
            'facture_livraison_id' => $facture->id,
            'cible'                => $request->validated('cible'),
            'type_deduction'       => $request->validated('type_deduction'),
            'montant'              => $request->validated('montant'),
            'commentaire'          => $request->validated('commentaire'),
        ]);

        return $this->createdResponse($deduction, 'Déduction ajoutée avec succès');
    }
}
