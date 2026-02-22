<?php

namespace App\Http\Requests\Livraison;

use App\Enums\CibleDeduction;
use App\Enums\TypeDeduction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Déduction de commission liée à une facture de livraison (workflow simplifié).
 */
class StoreDeductionFactureRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'cible'          => ['required', Rule::in(CibleDeduction::values())],
            'type_deduction' => ['required', Rule::in(TypeDeduction::values())],
            'montant'        => ['required', 'numeric', 'min:0.01'],
            'commentaire'    => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'cible.required'          => 'La cible de la déduction est obligatoire.',
            'cible.in'                => 'La cible doit être : ' . implode(', ', CibleDeduction::values()) . '.',
            'type_deduction.required' => 'Le type de déduction est obligatoire.',
            'type_deduction.in'       => 'Le type doit être : ' . implode(', ', TypeDeduction::values()) . '.',
            'montant.required'        => 'Le montant de la déduction est obligatoire.',
            'montant.min'             => 'Le montant doit être supérieur à 0.',
        ];
    }
}
