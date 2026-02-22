<?php

namespace App\Http\Requests\Livraison;

use Illuminate\Foundation\Http\FormRequest;

class StoreFactureLivraisonRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'sortie_vehicule_id' => ['required', 'integer', 'exists:sorties_vehicules,id', 'unique:factures_livraisons,sortie_vehicule_id'],
            'montant_brut'       => ['required', 'numeric', 'min:0'],
            'montant_net'        => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'sortie_vehicule_id.required' => 'La sortie véhicule est obligatoire.',
            'sortie_vehicule_id.exists'   => 'La sortie véhicule sélectionnée n\'existe pas.',
            'sortie_vehicule_id.unique'   => 'Une facture existe déjà pour cette sortie véhicule.',
            'montant_brut.required'       => 'Le montant brut est obligatoire.',
            'montant_brut.min'            => 'Le montant brut doit être positif.',
        ];
    }
}
