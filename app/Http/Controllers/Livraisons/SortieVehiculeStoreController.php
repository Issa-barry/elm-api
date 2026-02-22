<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Livraison\StoreSortieVehiculeRequest;
use App\Http\Traits\ApiResponse;
use App\Models\SortieVehicule;
use App\Models\Vehicule;

class SortieVehiculeStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreSortieVehiculeRequest $request)
    {
        $vehicule = Vehicule::find($request->validated('vehicule_id'));

        if (!$vehicule) {
            return $this->notFoundResponse('Véhicule non trouvé');
        }

        // Règle : packs_charges <= capacite_packs
        if ($request->validated('packs_charges') > $vehicule->capacite_packs) {
            return $this->validationErrorResponse(
                ['packs_charges' => ["Le nombre de packs chargés ({$request->validated('packs_charges')}) dépasse la capacité du véhicule ({$vehicule->capacite_packs})."]],
                'Données invalides'
            );
        }

        // Règle : pas de sortie en cours pour ce véhicule
        if ($vehicule->sortieEnCours()->exists()) {
            return $this->errorResponse('Ce véhicule a déjà une sortie en cours.', null, 409);
        }

        $sortie = SortieVehicule::create([
            'vehicule_id'                      => $vehicule->id,
            'livreur_id_effectif'              => $request->validated('livreur_id_effectif'),
            'packs_charges'                    => $request->validated('packs_charges'),
            'date_depart'                      => $request->input('date_depart', now()),
            'statut_sortie'                    => 'en_cours',
            // Snapshots
            'snapshot_mode_commission'         => $vehicule->mode_commission->value,
            'snapshot_valeur_commission'       => $vehicule->valeur_commission,
            'snapshot_pourcentage_proprietaire' => $vehicule->pourcentage_proprietaire,
            'snapshot_pourcentage_livreur'     => $vehicule->pourcentage_livreur,
        ]);

        $sortie->load(['vehicule', 'livreurEffectif']);

        return $this->createdResponse($sortie, 'Sortie véhicule créée avec succès');
    }
}
