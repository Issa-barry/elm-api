<?php

namespace App\Http\Requests\Livraison;

use App\Enums\ModeCommission;
use App\Enums\TypeVehicule;
use App\Services\UsineContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du payload one-shot : vehicule + proprietaire + livreur en une requete.
 * Envoi en multipart/form-data (photo facultative).
 */
class StoreVehiculeOneShotRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function prepareForValidation(): void
    {
        if ($this->has('proprietaire.phone')) {
            $this->merge([
                'proprietaire' => array_merge(
                    (array) $this->input('proprietaire', []),
                    ['phone' => preg_replace('/[^0-9+]/', '', $this->input('proprietaire.phone', ''))],
                ),
            ]);
        }

        if ($this->has('livreur.phone')) {
            $this->merge([
                'livreur' => array_merge(
                    (array) $this->input('livreur', []),
                    ['phone' => preg_replace('/[^0-9+]/', '', $this->input('livreur.phone', ''))],
                ),
            ]);
        }

        if ($this->exists('vehicule.type_vehicule')) {
            $vehicule = (array) $this->input('vehicule', []);
            $typeVehicule = TypeVehicule::normalize($this->input('vehicule.type_vehicule'));

            $vehicule['type_vehicule'] = $typeVehicule;

            $capaciteManquante = !array_key_exists('capacite_packs', $vehicule)
                || $vehicule['capacite_packs'] === ''
                || $vehicule['capacite_packs'] === null;
            if ($capaciteManquante && $typeVehicule !== null) {
                $capaciteParDefaut = TypeVehicule::defaultCapacitePacks($typeVehicule);

                if ($capaciteParDefaut !== null) {
                    $vehicule['capacite_packs'] = $capaciteParDefaut;
                }
            }

            $this->merge(['vehicule' => $vehicule]);
        }
    }

    public function rules(): array
    {
        $usineId = app(UsineContext::class)->getCurrentUsineId();

        return [
            'vehicule'                           => ['required', 'array'],
            'vehicule.nom_vehicule'              => ['required_without_all:vehicule.marque,vehicule.modele', 'nullable', 'string', 'max:100'],
            'vehicule.marque'                    => ['sometimes', 'nullable', 'string', 'max:100'],
            'vehicule.modele'                    => ['sometimes', 'nullable', 'string', 'max:100'],
            'vehicule.immatriculation'           => [
                'required', 'string', 'max:20',
                Rule::unique('vehicules', 'immatriculation')->where('usine_id', $usineId),
            ],
            'vehicule.type_vehicule'             => ['required', Rule::in(TypeVehicule::allowedValues())],
            'vehicule.capacite_packs'            => ['nullable', 'integer', 'min:1'],
            'vehicule.mode_commission'           => ['required', Rule::in(ModeCommission::values())],
            'vehicule.valeur_commission'         => ['required', 'numeric', 'min:0'],
            'vehicule.pourcentage_proprietaire'  => ['required', 'numeric', 'min:0', 'max:100'],
            'vehicule.pourcentage_livreur'       => ['required', 'numeric', 'min:0', 'max:100'],

            'proprietaire'          => ['required', 'array'],
            'proprietaire.nom'      => ['required', 'string', 'max:100'],
            'proprietaire.prenom'   => ['required', 'string', 'max:100'],
            'proprietaire.phone'    => ['required', 'string', 'max:30'],
            'proprietaire.pays'     => ['sometimes', 'nullable', 'string', 'max:50'],
            'proprietaire.ville'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'proprietaire.quartier' => ['sometimes', 'nullable', 'string', 'max:100'],
            'proprietaire.email'    => ['sometimes', 'nullable', 'email', 'max:255'],

            'livreur'        => ['required', 'array'],
            'livreur.nom'    => ['required', 'string', 'max:100'],
            'livreur.prenom' => ['required', 'string', 'max:100'],
            'livreur.phone'  => ['required', 'string', 'max:30'],

            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $proprio  = (float) $this->input('vehicule.pourcentage_proprietaire', 0);
            $livreur  = (float) $this->input('vehicule.pourcentage_livreur', 0);
            if (abs(($proprio + $livreur) - 100) > 0.01) {
                $v->errors()->add(
                    'vehicule.pourcentage_livreur',
                    'La somme des pourcentages proprietaire + livreur doit etre egale a 100.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'vehicule.required'                          => 'Les donnees du vehicule sont obligatoires.',
            'vehicule.nom_vehicule.required_without_all'  => 'Le nom du vehicule est obligatoire si marque et modele ne sont pas fournis.',
            'vehicule.immatriculation.required'          => 'L\'immatriculation est obligatoire.',
            'vehicule.immatriculation.unique'            => 'Cette immatriculation est deja utilisee pour cette usine.',
            'vehicule.type_vehicule.required'            => 'Le type de vehicule est obligatoire.',
            'vehicule.type_vehicule.in'                  => 'Le type de vehicule doit etre : ' . implode(', ', TypeVehicule::allowedValues()) . '.',
            'vehicule.capacite_packs.min'               => 'La capacite en packs doit etre au minimum 1.',
            'vehicule.mode_commission.required'          => 'Le mode de commission est obligatoire.',
            'vehicule.valeur_commission.required'        => 'La valeur de commission est obligatoire.',
            'vehicule.pourcentage_proprietaire.required' => 'Le pourcentage proprietaire est obligatoire.',
            'vehicule.pourcentage_livreur.required'      => 'Le pourcentage livreur est obligatoire.',
            'proprietaire.required'                      => 'Les donnees du proprietaire sont obligatoires.',
            'proprietaire.nom.required'                  => 'Le nom du proprietaire est obligatoire.',
            'proprietaire.prenom.required'               => 'Le prenom du proprietaire est obligatoire.',
            'proprietaire.phone.required'                => 'Le telephone du proprietaire est obligatoire.',
            'livreur.required'                           => 'Les donnees du livreur sont obligatoires.',
            'livreur.nom.required'                       => 'Le nom du livreur est obligatoire.',
            'livreur.prenom.required'                    => 'Le prenom du livreur est obligatoire.',
            'livreur.phone.required'                     => 'Le telephone du livreur est obligatoire.',
            'photo.image'                                => 'Le fichier doit etre une image.',
            'photo.mimes'                                => 'La photo doit etre en format jpg, jpeg, png ou webp.',
            'photo.max'                                  => 'La photo ne peut pas depasser 3 Mo.',
        ];
    }
}
