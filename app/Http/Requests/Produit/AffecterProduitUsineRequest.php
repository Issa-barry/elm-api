<?php

namespace App\Http\Requests\Produit;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AffecterProduitUsineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id'   => 'required|integer|exists:sites,id',
            'is_active'  => 'nullable|boolean',
            'prix_usine' => 'nullable|integer|min:0',
            'prix_achat' => 'nullable|integer|min:0',
            'prix_vente' => 'nullable|integer|min:0',
            'cout'       => 'nullable|integer|min:0',
            'tva'        => 'nullable|integer|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'site_id.required' => "L'identifiant du site est obligatoire.",
            'site_id.exists'   => "Le site spécifié n'existe pas.",
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Les données fournies sont invalides.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
