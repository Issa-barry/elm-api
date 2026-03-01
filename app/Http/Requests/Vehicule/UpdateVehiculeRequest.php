<?php

namespace App\Http\Requests\Vehicule;

use App\Enums\TypeVehicule;
use App\Services\UsineContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehiculeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function prepareForValidation(): void
    {
        $dataToMerge = [];

        // Normalisation du type de véhicule et capacité par défaut
        if ($this->exists('type_vehicule')) {
            $typeVehicule                = TypeVehicule::normalize($this->input('type_vehicule'));
            $dataToMerge['type_vehicule'] = $typeVehicule;

            if (
                (
                    !$this->exists('capacite_packs')
                    || $this->input('capacite_packs') === ''
                    || $this->input('capacite_packs') === null
                )
                && $typeVehicule !== null
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
        $id      = $this->route('id');
        $usineId = $this->input('usine_id') ?? app(UsineContext::class)->getCurrentUsineId();

        return [
            'usine_id'                 => ['sometimes', 'integer', 'exists:usines,id'],
            'nom_vehicule'             => ['sometimes', 'string', 'max:100'],
            'marque'                   => ['sometimes', 'nullable', 'string', 'max:100'],
            'modele'                   => ['sometimes', 'nullable', 'string', 'max:100'],
            'immatriculation'          => [
                'sometimes', 'string', 'max:20',
                Rule::unique('vehicules', 'immatriculation')->where('usine_id', $usineId)->ignore($id),
            ],
            'type_vehicule'            => ['sometimes', Rule::in(TypeVehicule::allowedValues())],
            'capacite_packs'           => ['sometimes', 'integer', 'min:1'],
            'proprietaire_id'          => ['sometimes', 'integer', 'exists:proprietaires,id'],
            'livreur_principal_id'     => ['nullable', 'integer', 'exists:livreurs,id'],
            'pris_en_charge_par_usine' => ['sometimes', 'boolean'],
            'photo'                    => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'is_active'                => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'usine_id.exists'        => 'L\'usine sélectionnée n\'existe pas.',
            'immatriculation.unique' => 'Ce numero d\'immatriculation est deja utilise pour cette usine.',
            'type_vehicule.in'       => 'Le type de vehicule doit etre : ' . implode(', ', TypeVehicule::allowedValues()) . '.',
            'photo.image'            => 'Le fichier doit etre une image.',
            'photo.mimes'            => 'La photo doit etre au format jpg, jpeg, png ou webp.',
            'photo.max'              => 'La photo ne peut pas depasser 3 Mo.',
        ];
    }
}
