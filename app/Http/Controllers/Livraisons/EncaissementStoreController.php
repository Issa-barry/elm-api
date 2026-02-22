<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Livraison\StoreEncaissementRequest;
use App\Http\Traits\ApiResponse;
use App\Models\EncaissementLivraison;
use App\Models\FactureLivraison;

class EncaissementStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreEncaissementRequest $request)
    {
        $facture = FactureLivraison::find($request->validated('facture_livraison_id'));

        if (!$facture) {
            return $this->notFoundResponse('Facture non trouvée');
        }

        // Contrôle : encaissements cumulés <= montant_net
        $dejaEncaisse = $facture->encaissements()->sum('montant');
        $nouveau      = (float) $request->validated('montant');
        $total        = $dejaEncaisse + $nouveau;

        if ($total > (float) $facture->montant_net) {
            return $this->validationErrorResponse(
                ['montant' => [
                    "Le cumul des encaissements ({$total}) dépasserait le montant net de la facture ({$facture->montant_net})."
                ]],
                'Dépassement du montant facturé'
            );
        }

        $encaissement = EncaissementLivraison::create($request->validated());

        // Mise à jour automatique du statut facture
        $facture->recalculStatut();

        return $this->createdResponse($encaissement->load('facture'), 'Encaissement enregistré');
    }
}
