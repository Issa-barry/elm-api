<?php

namespace App\Http\Requests\PaiementPacking;

use App\Models\PaiementPacking;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StorePaiementPackingRequest extends FormRequest
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
                Rule::exists('prestataires', 'id')->whereNull('deleted_at'),
            ],
            'periode_debut' => 'required|date',
            'periode_fin' => 'required|date|after_or_equal:periode_debut',
            'date_paiement' => 'required|date',
            'mode_paiement' => ['nullable', Rule::in(array_keys(PaiementPacking::MODES_PAIEMENT))],
            'notes' => 'nullable|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'prestataire_id.required' => 'Le prestataire est obligatoire.',
            'prestataire_id.exists' => 'Le prestataire sélectionné n\'existe pas.',
            'periode_debut.required' => 'La date de début de période est obligatoire.',
            'periode_debut.date' => 'La date de début de période n\'est pas valide.',
            'periode_fin.required' => 'La date de fin de période est obligatoire.',
            'periode_fin.date' => 'La date de fin de période n\'est pas valide.',
            'periode_fin.after_or_equal' => 'La date de fin doit être égale ou postérieure à la date de début.',
            'date_paiement.required' => 'La date de paiement est obligatoire.',
            'date_paiement.date' => 'La date de paiement n\'est pas valide.',
            'mode_paiement.in' => 'Le mode de paiement doit être : especes, virement, cheque ou mobile_money.',
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
