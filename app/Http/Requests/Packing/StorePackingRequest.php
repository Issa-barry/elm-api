<?php

namespace App\Http\Requests\Packing;

use App\Enums\PackingStatut;
use App\Models\Packing;
use App\Models\Parametre;
use App\Services\UsineContext;
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
        $usineId = app(UsineContext::class)->getCurrentUsineId();

        // prestataire_id doit appartenir à la même usine (protection cross-usine)
        $prestataireRule = $usineId
            ? Rule::exists('prestataires', 'id')->where('usine_id', $usineId)
            : Rule::exists('prestataires', 'id');

        // facture_id doit appartenir à la même usine (protection cross-usine)
        $factureRule = $usineId
            ? Rule::exists('facture_packings', 'id')->where('usine_id', $usineId)
            : Rule::exists('facture_packings', 'id');

        return [
            'prestataire_id'   => ['required', 'integer', $prestataireRule],
            'date'             => ['required', 'date'],
            'nb_rouleaux'      => ['required', 'integer', 'min:0'],
            'prix_par_rouleau' => ['required', 'integer', 'min:0'],
            'statut'           => ['nullable', Rule::enum(PackingStatut::class)],
            'facture_id'       => ['nullable', 'integer', $factureRule],
            'notes'            => ['nullable', 'string'],
            'montant'          => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('prix_par_rouleau') || $this->input('prix_par_rouleau') === null || $this->input('prix_par_rouleau') === '') {
            $this->merge([
                'prix_par_rouleau' => Parametre::getPrixRouleauDefaut(),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $targetStatut = $this->input('statut', Packing::STATUT_DEFAUT);
            if ($targetStatut !== PackingStatut::VALIDE->value) {
                return;
            }

            $nbRouleaux = (int) $this->input('nb_rouleaux', 0);
            if ($nbRouleaux <= 0) {
                return;
            }

            $produit = Parametre::getProduitRouleau();
            if (!$produit) {
                return;
            }

            if ($produit->qte_stock < $nbRouleaux) {
                $validator->errors()->add(
                    'nb_rouleaux',
                    "Stock insuffisant. Stock disponible : {$produit->qte_stock} rouleaux."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'prestataire_id.required' => 'Le prestataire est obligatoire.',
            'prestataire_id.integer' => 'Le prestataire est invalide.',
            'prestataire_id.exists' => 'Le prestataire selectionne est introuvable.',
            'date.required' => 'La date est obligatoire.',
            'date.date' => 'La date est invalide.',
            'nb_rouleaux.required' => 'Le nombre de rouleaux est obligatoire.',
            'nb_rouleaux.integer' => 'Le nombre de rouleaux doit etre un entier.',
            'nb_rouleaux.min' => 'Le nombre de rouleaux ne peut pas etre negatif.',
            'prix_par_rouleau.required' => 'Le prix par rouleau est obligatoire.',
            'prix_par_rouleau.integer' => 'Le prix par rouleau doit etre un entier.',
            'prix_par_rouleau.min' => 'Le prix par rouleau ne peut pas etre negatif.',
            'statut.enum' => 'Le statut doit etre : a_valider, valide ou annule.',
            'facture_id.integer' => 'La facture est invalide.',
            'facture_id.exists' => 'La facture fournie est introuvable.',
            'notes.string' => 'Les notes doivent etre une chaine de caracteres.',
            'montant.prohibited' => 'Le montant est calcule automatiquement par le serveur.',
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
