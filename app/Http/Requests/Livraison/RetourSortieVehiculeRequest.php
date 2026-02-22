<?php

namespace App\Http\Requests\Livraison;

use Illuminate\Foundation\Http\FormRequest;

class RetourSortieVehiculeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'packs_retour' => ['required', 'integer', 'min:0'],
            'date_retour'  => ['sometimes', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'packs_retour.required' => 'Le nombre de packs retournés est obligatoire.',
            'packs_retour.min'      => 'Le nombre de packs retournés ne peut pas être négatif.',
        ];
    }
}
