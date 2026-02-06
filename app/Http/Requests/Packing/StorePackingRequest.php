<?php

namespace App\Http\Requests\Packing;

use App\Models\Packing;
use App\Models\Prestataire;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StorePackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prestataire_id' => [
                'required',
                'integer',
                Rule::exists('prestataires', 'id')->where(function ($query) {
                    $query->where('type', Prestataire::TYPE_MACHINISTE)
                          ->whereNull('deleted_at');
                }),
            ],
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'nb_rouleaux' => 'required|integer|min:1',
            'prix_par_rouleau' => 'required|integer|min:0',
            'statut' => ['nullable', Rule::in(array_keys(Packing::STATUTS))],
            'notes' => 'nullable|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'prestataire_id.required' => 'Le prestataire est obligatoire.',
            'prestataire_id.exists' => 'Le prestataire sélectionné doit être un machiniste actif.',
            'date_debut.required' => 'La date de début est obligatoire.',
            'date_debut.date' => 'La date de début n\'est pas valide.',
            'date_fin.required' => 'La date de fin est obligatoire.',
            'date_fin.date' => 'La date de fin n\'est pas valide.',
            'date_fin.after_or_equal' => 'La date de fin doit être égale ou postérieure à la date de début.',
            'nb_rouleaux.required' => 'Le nombre de rouleaux est obligatoire.',
            'nb_rouleaux.integer' => 'Le nombre de rouleaux doit être un nombre entier.',
            'nb_rouleaux.min' => 'Le nombre de rouleaux doit être au moins 1.',
            'prix_par_rouleau.required' => 'Le prix par rouleau est obligatoire.',
            'prix_par_rouleau.integer' => 'Le prix par rouleau doit être un nombre entier.',
            'prix_par_rouleau.min' => 'Le prix par rouleau ne peut pas être négatif.',
            'statut.in' => 'Le statut doit être : en_cours, termine, paye ou annule.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Les données fournies sont invalides.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
