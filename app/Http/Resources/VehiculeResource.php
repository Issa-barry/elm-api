<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehiculeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'usine_id'                 => $this->usine_id,
            'nom_vehicule'             => $this->nom_vehicule,
            'marque'                   => $this->marque,
            'modele'                   => $this->modele,
            'immatriculation'          => $this->immatriculation,
            'type_vehicule'            => $this->type_vehicule instanceof \BackedEnum
                                            ? $this->type_vehicule->value
                                            : $this->type_vehicule,
            'capacite_packs'           => $this->capacite_packs,
            'proprietaire_id'          => $this->proprietaire_id,
            'proprietaire'             => ProprietaireResource::make($this->whenLoaded('proprietaire')),
            'livreur_principal_id'     => $this->livreur_principal_id,
            'livreur_principal'        => LivreurResource::make($this->whenLoaded('livreurPrincipal')),
            'pris_en_charge_par_usine' => $this->pris_en_charge_par_usine,
            'mode_commission'          => $this->mode_commission instanceof \BackedEnum
                                            ? $this->mode_commission->value
                                            : $this->mode_commission,
            'valeur_commission'        => (float) $this->valeur_commission,
            'pourcentage_proprietaire' => (float) $this->pourcentage_proprietaire,
            'pourcentage_livreur'      => (float) $this->pourcentage_livreur,
            'photo_path'               => $this->photo_path,
            'photo_url'                => $this->photo_url,
            'is_active'                => $this->is_active,
            'created_at'               => $this->created_at?->toISOString(),
            'updated_at'               => $this->updated_at?->toISOString(),
        ];
    }
}
