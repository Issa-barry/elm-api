<?php

namespace App\Http\Requests\Packing;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdatePackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prestataire_id'   => ['sometimes', 'integer', Rule::exists('prestataires', 'id')],
            'date'             => ['sometimes', 'date'],
            'nb_rouleaux'      => ['sometimes', 'integer', 'min:1', 'max:9999999'],
            'prix_par_rouleau' => ['sometimes', 'integer', 'min:0', 'max:99999999'],
            'notes'            => ['sometimes', 'nullable', 'string', 'max:5000'],
            'montant'          => ['prohibited'],
            'statut'           => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'prestataire_id.integer' => 'Le prestataire est invalide.',
            'prestataire_id.exists' => 'Le prestataire selectionne est introuvable.',
            'date.date' => 'La date est invalide.',
            'nb_rouleaux.integer' => 'Le nombre de rouleaux doit etre un entier.',
            'nb_rouleaux.min' => 'Le nombre de rouleaux doit etre superieur a 0.',
            'nb_rouleaux.max' => 'Le nombre de rouleaux ne peut pas depasser 9 999 999.',
            'prix_par_rouleau.integer' => 'Le prix par rouleau doit etre un entier.',
            'prix_par_rouleau.min' => 'Le prix par rouleau ne peut pas etre negatif.',
            'prix_par_rouleau.max' => 'Le prix par rouleau ne peut pas depasser 99 999 999.',
            'notes.string'      => 'Les notes doivent etre une chaine de caracteres.',
            'notes.max'         => 'Les notes ne peuvent pas depasser 5000 caracteres.',
            'montant.prohibited'=> 'Le montant est calcule automatiquement par le serveur.',
            'statut.prohibited' => 'Le statut ne peut pas etre modifie directement. Utilisez PATCH /{id}/statut.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Les donnees fournies sont invalides.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
