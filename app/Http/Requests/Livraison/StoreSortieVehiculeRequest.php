<?php

namespace App\Http\Requests\Livraison;

use Illuminate\Foundation\Http\FormRequest;

class StoreSortieVehiculeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'vehicule_id'       => ['required', 'integer', 'exists:vehicules,id'],
            'livreur_id_effectif' => ['required', 'integer', 'exists:livreurs,id'],
            'packs_charges'     => ['required', 'integer', 'min:1'],
            'date_depart'       => ['sometimes', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicule_id.required'        => 'Le véhicule est obligatoire.',
            'vehicule_id.exists'          => 'Le véhicule sélectionné n\'existe pas.',
            'livreur_id_effectif.required' => 'Le livreur effectif est obligatoire.',
            'livreur_id_effectif.exists'  => 'Le livreur sélectionné n\'existe pas.',
            'packs_charges.required'      => 'Le nombre de packs chargés est obligatoire.',
            'packs_charges.min'           => 'Le nombre de packs chargés doit être au minimum 1.',
        ];
    }
}
