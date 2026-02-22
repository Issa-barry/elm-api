<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Livraison\StoreVehiculeOneShotRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Livreur;
use App\Models\Proprietaire;
use App\Models\Vehicule;
use Illuminate\Support\Facades\DB;

/**
 * Creation one-shot : vehicule + proprietaire + livreur en une seule requete.
 */
class VehiculeOneShotController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreVehiculeOneShotRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $proprioPhone = $request->input('proprietaire.phone');

            $proprietaire = Proprietaire::firstOrCreate(
                ['phone' => $proprioPhone],
                [
                    'nom'      => mb_strtoupper($request->input('proprietaire.nom')),
                    'prenom'   => mb_convert_case($request->input('proprietaire.prenom'), MB_CASE_TITLE, 'UTF-8'),
                    'pays'     => $request->input('proprietaire.pays'),
                    'ville'    => $request->input('proprietaire.ville'),
                    'quartier' => $request->input('proprietaire.quartier'),
                    'email'    => $request->input('proprietaire.email'),
                ]
            );

            $livreurPhone = $request->input('livreur.phone');

            $livreur = Livreur::firstOrCreate(
                ['phone' => $livreurPhone],
                [
                    'nom'    => mb_strtoupper($request->input('livreur.nom')),
                    'prenom' => mb_convert_case($request->input('livreur.prenom'), MB_CASE_TITLE, 'UTF-8'),
                ]
            );

            $photoPath = $request->hasFile('photo')
                ? $request->file('photo')->store('vehicules', 'public')
                : null;

            $marque = $request->input('vehicule.marque');
            $modele = $request->input('vehicule.modele');
            $nomVehicule = $request->input('vehicule.nom_vehicule')
                ?? trim(implode(' ', array_filter([$marque, $modele])));

            $vehicule = Vehicule::create([
                'nom_vehicule'             => $nomVehicule,
                'marque'                   => $marque,
                'modele'                   => $modele,
                'immatriculation'          => $request->input('vehicule.immatriculation'),
                'type_vehicule'            => $request->input('vehicule.type_vehicule'),
                'capacite_packs'           => (int) $request->input('vehicule.capacite_packs'),
                'proprietaire_id'          => $proprietaire->id,
                'livreur_principal_id'     => $livreur->id,
                'pris_en_charge_par_usine' => filter_var(
                    $request->input('vehicule.pris_en_charge_par_usine', false),
                    FILTER_VALIDATE_BOOLEAN
                ),
                'mode_commission'          => $request->input('vehicule.mode_commission'),
                'valeur_commission'        => (float) $request->input('vehicule.valeur_commission'),
                'pourcentage_proprietaire' => (float) $request->input('vehicule.pourcentage_proprietaire'),
                'pourcentage_livreur'      => (float) $request->input('vehicule.pourcentage_livreur'),
                'photo_path'               => $photoPath,
                'is_active'                => true,
            ]);

            $vehicule->load(['proprietaire', 'livreurPrincipal']);

            return $this->createdResponse([
                'vehicule'     => $vehicule,
                'proprietaire' => $proprietaire,
                'livreur'      => $livreur,
            ], 'Vehicule, proprietaire et livreur crees avec succes');
        });
    }
}
