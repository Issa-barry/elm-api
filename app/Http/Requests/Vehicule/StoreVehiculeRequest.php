<?php

namespace App\Http\Requests\Vehicule;

use App\Enums\TypeVehicule;
use App\Services\SiteContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehiculeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function prepareForValidation(): void
    {
        $dataToMerge = [];

        if (!$this->exists('site_id') || $this->input('site_id') === null) {
            $siteId = app(SiteContext::class)->getCurrentSiteId();
            if ($siteId !== null) {
                $dataToMerge['site_id'] = $siteId;
            }
        }

        $typeVehicule = TypeVehicule::normalize($this->input('type_vehicule'));

        if ($typeVehicule !== null) {
            $dataToMerge['type_vehicule'] = $typeVehicule;

            if (
                !$this->exists('capacite_packs')
                || $this->input('capacite_packs') === ''
                || $this->input('capacite_packs') === null
            ) {
                $capaciteParDefaut = TypeVehicule::defaultCapacitePacks($typeVehicule);
                if ($capaciteParDefaut !== null) {
                    $dataToMerge['capacite_packs'] = $capaciteParDefaut;
                }
            }
        }

        // Formatage de la qualité des données
        if ($this->has('nom_vehicule') && $this->input('nom_vehicule') !== null) {
            $dataToMerge['nom_vehicule'] = mb_convert_case(mb_strtolower($this->input('nom_vehicule')), MB_CASE_TITLE, 'UTF-8');
        }

        if ($this->has('marque') && $this->input('marque') !== null) {
            $dataToMerge['marque'] = mb_convert_case(mb_strtolower($this->input('marque')), MB_CASE_TITLE, 'UTF-8');
        }

        if ($this->has('modele') && $this->input('modele') !== null) {
            $dataToMerge['modele'] = mb_convert_case(mb_strtolower($this->input('modele')), MB_CASE_TITLE, 'UTF-8');
        }

        if ($this->has('immatriculation') && $this->input('immatriculation') !== null) {
            $dataToMerge['immatriculation'] = mb_strtoupper(trim($this->input('immatriculation')), 'UTF-8');
        }

        if (!empty($dataToMerge)) {
            $this->merge($dataToMerge);
        }
    }

    public function rules(): array
    {
        return [
            'site_id'                 => ['required', 'integer', 'exists:sites,id'],
            'nom_vehicule'             => ['required', 'string', 'max:100'],
            'marque'                   => ['nullable', 'string', 'max:100'],
            'modele'                   => ['nullable', 'string', 'max:100'],
            'immatriculation'          => [
                'required', 'string', 'max:20',
                Rule::unique('vehicules', 'immatriculation')->where('site_id', $this->input('site_id')),
            ],
            'type_vehicule'            => ['required', Rule::in(TypeVehicule::allowedValues())],
            'capacite_packs'           => ['nullable', 'integer', 'min:1'],
            'proprietaire_id'          => ['required', 'integer', 'exists:proprietaires,id'],
            'livreur_principal_id'     => ['nullable', 'integer', 'exists:livreurs,id'],
            'pris_en_charge_par_usine' => ['sometimes', 'boolean'],
            'photo'                    => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'is_active'                => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom_vehicule.required'       => 'Le nom du vehicule est obligatoire.',
            'immatriculation.required'    => 'L\'immatriculation est obligatoire.',
            'site_id.required'           => 'L\'usine est obligatoire.',
            'site_id.exists'             => 'L\'usine sélectionnée n\'existe pas.',
            'immatriculation.unique'      => 'Ce numero d\'immatriculation est deja utilise pour cette usine.',
            'type_vehicule.required'      => 'Le type de vehicule est obligatoire.',
            'type_vehicule.in'            => 'Le type de vehicule doit etre : ' . implode(', ', TypeVehicule::allowedValues()) . '.',
            'capacite_packs.min'          => 'La capacite doit etre au minimum 1 pack.',
            'proprietaire_id.required'    => 'Le proprietaire est obligatoire.',
            'proprietaire_id.exists'      => 'Le proprietaire selectionne n\'existe pas.',
            'livreur_principal_id.exists' => 'Le livreur selectionne n\'existe pas.',
            'photo.image'                 => 'Le fichier doit etre une image.',
            'photo.mimes'                 => 'La photo doit etre au format jpg, jpeg, png ou webp.',
            'photo.max'                   => 'La photo ne peut pas depasser 3 Mo.',
        ];
    }
}
