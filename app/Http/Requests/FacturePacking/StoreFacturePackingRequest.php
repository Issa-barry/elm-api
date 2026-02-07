<?php

namespace App\Http\Requests\FacturePacking;

use App\Models\FacturePacking;
use App\Models\Parametre;
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
            'periode' => 'nullable|integer|in:1,2',
            'mois' => 'nullable|integer|min:1|max:12',
            'annee' => 'nullable|integer|min:2020|max:2100',
            'periode_debut' => 'required_without:periode|date',
            'periode_fin' => 'required_without:periode|date|after_or_equal:periode_debut',
            'statut' => ['nullable', Rule::in(array_keys(FacturePacking::STATUTS))],
            'notes' => 'nullable|string|max:5000',
        ];
    }

    /**
     * Préparer les données pour la validation
     */
    protected function prepareForValidation(): void
    {
        // Si une période est spécifiée, calculer les dates automatiquement
        if ($this->has('periode') && $this->periode) {
            $mois = $this->integer('mois', (int) now()->format('m'));
            $annee = $this->integer('annee', (int) now()->format('Y'));
            $dates = Parametre::getPeriodeDates($this->integer('periode'), $mois, $annee);

            $this->merge([
                'periode_debut' => $dates['debut'],
                'periode_fin' => $dates['fin'],
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'prestataire_id.required' => 'Le prestataire est obligatoire.',
            'prestataire_id.exists' => 'Le prestataire sélectionné doit être un machiniste actif.',
            'periode.in' => 'La période doit être 1 (1ère quinzaine) ou 2 (2ème quinzaine).',
            'periode_debut.required_without' => 'La date de début est obligatoire si aucune période n\'est spécifiée.',
            'periode_debut.date' => 'La date de début n\'est pas valide.',
            'periode_fin.required_without' => 'La date de fin est obligatoire si aucune période n\'est spécifiée.',
            'periode_fin.date' => 'La date de fin n\'est pas valide.',
            'periode_fin.after_or_equal' => 'La date de fin doit être égale ou postérieure à la date de début.',
            'statut.in' => 'Le statut doit être : brouillon, validee ou annulee.',
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
