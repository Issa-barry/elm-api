<?php

namespace App\Http\Requests\Livraison;

use App\Enums\ModePaiement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEncaissementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'facture_livraison_id' => ['required', 'integer', 'exists:factures_livraisons,id'],
            'montant'              => ['required', 'numeric', 'min:0.01'],
            'date_encaissement'    => ['required', 'date'],
            'mode_paiement'        => ['required', Rule::in(ModePaiement::values())],
            'note'                 => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'facture_livraison_id.required' => 'La facture est obligatoire.',
            'facture_livraison_id.exists'   => 'La facture sélectionnée n\'existe pas.',
            'montant.required'              => 'Le montant est obligatoire.',
            'montant.min'                   => 'Le montant doit être supérieur à 0.',
            'date_encaissement.required'    => 'La date d\'encaissement est obligatoire.',
            'mode_paiement.required'        => 'Le mode de paiement est obligatoire.',
            'mode_paiement.in'              => 'Le mode de paiement doit être : ' . implode(', ', ModePaiement::values()) . '.',
        ];
    }
}
