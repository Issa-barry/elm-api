<?php

namespace App\Http\Requests\Packing;

use App\Enums\PackingStatut;
use App\Models\Parametre;
use App\Services\SiteContext;
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
        $siteId = app(SiteContext::class)->getCurrentSiteId();

        // prestataire_id doit appartenir à la même usine (protection cross-usine)
        $prestataireRule = $siteId
            ? Rule::exists('prestataires', 'id')->where('site_id', $siteId)
            : Rule::exists('prestataires', 'id');

        return [
            'prestataire_id'   => ['required', 'integer', $prestataireRule],
            'date'             => ['required', 'date'],
            'nb_rouleaux'      => ['required', 'integer', 'min:1', 'max:9999999'],
            'prix_par_rouleau' => ['required', 'integer', 'min:0', 'max:99999999'],
            'statut'           => ['nullable', Rule::enum(PackingStatut::class)],
            'notes'            => ['nullable', 'string', 'max:5000'],
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
            $statut = (string) $this->input('statut', PackingStatut::IMPAYEE->value);

            if (in_array($statut, [PackingStatut::PARTIELLE->value, PackingStatut::PAYEE->value], true)) {
                $validator->errors()->add(
                    'statut',
                    'Les statuts partielle et payee sont calcules automatiquement via les versements.'
                );
                return;
            }

            if ($statut === PackingStatut::ANNULEE->value) {
                return;
            }

            $nbRouleaux = (int) $this->input('nb_rouleaux', 0);

            if ($nbRouleaux <= 0) {
                return;
            }

            $produitId = Parametre::getProduitRouleauId();

            // Produit rouleau non configuré dans les paramètres
            if (!$produitId) {
                $validator->errors()->add(
                    'nb_rouleaux',
                    'Le produit rouleau n\'est pas configuré. Contactez un administrateur.'
                );
                return;
            }

            // Récupérer le stock pour l'usine courante
            $siteId       = app(SiteContext::class)->getCurrentSiteId();
            $stock         = \App\Models\Stock::where('produit_id', $produitId)
                ->where('site_id', $siteId)
                ->first();
            $qteDisponible = $stock?->qte_stock ?? 0;

            // Stock insuffisant — bloque toute création quel que soit le statut
            if ($qteDisponible < $nbRouleaux) {
                $validator->errors()->add(
                    'nb_rouleaux',
                    "Stock rouleau insuffisant, packing impossible. Stock disponible : {$qteDisponible} rouleaux."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'prestataire_id.required'   => 'Le prestataire est obligatoire.',
            'prestataire_id.integer'    => 'Le prestataire est invalide.',
            'prestataire_id.exists'     => 'Le prestataire selectionne est introuvable.',
            'date.required'             => 'La date est obligatoire.',
            'date.date'                 => 'La date est invalide.',
            'nb_rouleaux.required'      => 'Le nombre de rouleaux est obligatoire.',
            'nb_rouleaux.integer'       => 'Le nombre de rouleaux doit etre un entier.',
            'nb_rouleaux.min'           => 'Le nombre de rouleaux doit etre superieur a 0.',
            'nb_rouleaux.max'           => 'Le nombre de rouleaux ne peut pas depasser 9 999 999.',
            'prix_par_rouleau.required' => 'Le prix par rouleau est obligatoire.',
            'prix_par_rouleau.integer'  => 'Le prix par rouleau doit etre un entier.',
            'prix_par_rouleau.min'      => 'Le prix par rouleau ne peut pas etre negatif.',
            'prix_par_rouleau.max'      => 'Le prix par rouleau ne peut pas depasser 99 999 999.',
            'statut.enum'               => 'Le statut doit etre : impayee, partielle, payee ou annulee.',
            'notes.string'              => 'Les notes doivent etre une chaine de caracteres.',
            'notes.max'                 => 'Les notes ne peuvent pas depasser 5000 caracteres.',
            'montant.prohibited'        => 'Le montant est calcule automatiquement par le serveur.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Les donnees fournies sont invalides.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
