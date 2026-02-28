<?php

namespace App\Http\Requests\Produit;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProduitUsinePrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prix_usine' => 'nullable|integer|min:0',
            'prix_achat' => 'nullable|integer|min:0',
            'prix_vente' => 'nullable|integer|min:0',
            'cout'       => 'nullable|integer|min:0',
            'tva'        => 'nullable|integer|min:0|max:100',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $fields = ['prix_usine', 'prix_achat', 'prix_vente', 'cout', 'tva'];
            $hasAny = collect($fields)->some(fn ($f) => $this->has($f));

            if (!$hasAny) {
                $validator->errors()->add('prix', 'Au moins un champ de prix doit être fourni.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'prix_usine.integer' => 'Le prix usine doit être un entier (GNF).',
            'prix_achat.integer' => "Le prix d'achat doit être un entier (GNF).",
            'prix_vente.integer' => 'Le prix de vente doit être un entier (GNF).',
            'cout.integer'       => 'Le coût doit être un entier (GNF).',
            'tva.max'            => 'La TVA ne peut pas dépasser 100 %.',
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
