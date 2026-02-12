<?php

namespace App\Http\Requests\Packing;

use App\Models\Packing;
use App\Models\Parametre;
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
            'date' => 'required|date',
            'nb_rouleaux' => 'required|integer|min:1',
            'prix_par_rouleau' => 'nullable|integer|min:0',
            'statut' => ['nullable', Rule::in(array_keys(Packing::STATUTS))],
            'notes' => 'nullable|string|max:5000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $nbRouleaux = (int) $this->input('nb_rouleaux', 0);
            if ($nbRouleaux <= 0) {
                return;
            }

            $produit = Parametre::getProduitRouleau();
            if (!$produit) {
                return;
            }

            if ($produit->qte_stock <= 0) {
                $validator->errors()->add('nb_rouleaux', 'Stock de rouleaux épuisé (stock actuel : 0).');
            } elseif ($produit->qte_stock < $nbRouleaux) {
                $validator->errors()->add('nb_rouleaux', "Stock insuffisant. Stock disponible : {$produit->qte_stock} rouleaux.");
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('prix_par_rouleau') || $this->prix_par_rouleau === null) {
            $this->merge([
                'prix_par_rouleau' => Parametre::getPrixRouleauDefaut(),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'prestataire_id.required' => 'Le prestataire est obligatoire.',
            'prestataire_id.exists' => 'Le prestataire sélectionné doit être un machiniste actif.',
            'date.required' => 'La date est obligatoire.',
            'date.date' => 'La date n\'est pas valide.',
            'nb_rouleaux.required' => 'Le nombre de rouleaux est obligatoire.',
            'nb_rouleaux.integer' => 'Le nombre de rouleaux doit être un nombre entier.',
            'nb_rouleaux.min' => 'Le nombre de rouleaux doit être au moins 1.',
            'prix_par_rouleau.integer' => 'Le prix par rouleau doit être un nombre entier.',
            'prix_par_rouleau.min' => 'Le prix par rouleau ne peut pas être négatif.',
            'statut.in' => 'Le statut doit être : a_valider, valide ou annule.',
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
