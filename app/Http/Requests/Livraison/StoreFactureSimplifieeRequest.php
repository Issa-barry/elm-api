<?php

namespace App\Http\Requests\Livraison;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Création d'une facture de livraison liée directement au véhicule (workflow simplifié).
 * Les snapshots de commission sont capturés automatiquement depuis le véhicule.
 */
class StoreFactureSimplifieeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'vehicule_id'  => ['required', 'integer', 'exists:vehicules,id'],
            'packs_charges' => ['required', 'integer', 'min:1'],
            'montant_brut'  => ['required', 'numeric', 'min:0'],
            'montant_net'   => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicule_id.required'   => 'Le véhicule est obligatoire.',
            'vehicule_id.exists'     => 'Le véhicule sélectionné n\'existe pas.',
            'packs_charges.required' => 'Le nombre de packs chargés est obligatoire.',
            'packs_charges.min'      => 'Le nombre de packs doit être au minimum 1.',
            'montant_brut.required'  => 'Le montant brut est obligatoire.',
            'montant_brut.min'       => 'Le montant brut ne peut pas être négatif.',
        ];
    }
}
