<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Livraison\StoreFactureLivraisonRequest;
use App\Http\Traits\ApiResponse;
use App\Models\FactureLivraison;
use App\Models\SortieVehicule;

class FactureLivraisonStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreFactureLivraisonRequest $request)
    {
        $sortie = SortieVehicule::find($request->validated('sortie_vehicule_id'));

        if (!$sortie) {
            return $this->notFoundResponse('Sortie véhicule non trouvée');
        }

        if ($sortie->isEnCours()) {
            return $this->errorResponse('Impossible de créer une facture pour une sortie encore en cours.', null, 422);
        }

        $montantBrut = (float) $request->validated('montant_brut');
        $montantNet  = (float) $request->input('montant_net', $montantBrut);

        $facture = FactureLivraison::create([
            'sortie_vehicule_id' => $sortie->id,
            'montant_brut'       => $montantBrut,
            'montant_net'        => $montantNet,
            'statut_facture'     => 'emise',
        ]);

        $facture->load(['sortieVehicule.vehicule']);

        return $this->createdResponse($facture, 'Facture de livraison créée avec succès');
    }
}
