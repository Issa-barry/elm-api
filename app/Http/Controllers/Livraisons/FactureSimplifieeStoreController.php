<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Livraison\StoreFactureSimplifieeRequest;
use App\Http\Traits\ApiResponse;
use App\Models\FactureLivraison;
use App\Models\Vehicule;

/**
 * Création d'une facture de livraison liée directement au véhicule (workflow simplifié).
 * Les snapshots de commission (mode, valeur, pourcentages) sont capturés depuis le véhicule.
 */
class FactureSimplifieeStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreFactureSimplifieeRequest $request)
    {
        $vehicule = Vehicule::find($request->validated('vehicule_id'));

        if (!$vehicule) {
            return $this->notFoundResponse('Véhicule non trouvé');
        }

        $montantBrut = (float) $request->validated('montant_brut');
        $montantNet  = (float) $request->input('montant_net', $montantBrut);

        $facture = FactureLivraison::create([
            'vehicule_id'                       => $vehicule->id,
            'packs_charges'                     => (int) $request->validated('packs_charges'),
            'snapshot_mode_commission'          => $vehicule->mode_commission->value,
            'snapshot_valeur_commission'        => (float) $vehicule->valeur_commission,
            'snapshot_pourcentage_proprietaire' => (float) $vehicule->pourcentage_proprietaire,
            'snapshot_pourcentage_livreur'      => (float) $vehicule->pourcentage_livreur,
            'montant_brut'                      => $montantBrut,
            'montant_net'                       => $montantNet,
            'statut_facture'                    => 'emise',
        ]);

        $facture->load(['vehicule.proprietaire', 'vehicule.livreurPrincipal']);

        return $this->createdResponse($facture, 'Facture de livraison créée avec succès');
    }
}
