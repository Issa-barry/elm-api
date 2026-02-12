<?php

namespace App\Http\Requests\FacturePacking;

use App\Models\FacturePacking;
use App\Models\Prestataire;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreFacturePackingRequest extends FormRequest
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
            'date_paiement' => 'nullable|date',
            'mode_paiement' => ['nullable', Rule::in(array_keys(FacturePacking::MODES_PAIEMENT))],
            'statut' => ['nullable', Rule::in(array_keys(FacturePacking::STATUTS))],
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
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'date_paiement.date' => 'La date de paiement n\'est pas valide.',
            'mode_paiement.in' => 'Le mode de paiement doit être : especes, virement, cheque ou mobile_money.',
            'statut.in' => 'Le statut doit être : impayee, partielle, payee ou annulee.',
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
