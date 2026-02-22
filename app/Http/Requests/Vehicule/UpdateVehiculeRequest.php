<?php

namespace App\Http\Requests\Vehicule;

use App\Enums\ModeCommission;
use App\Enums\TypeVehicule;
use App\Services\UsineContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehiculeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function prepareForValidation(): void
    {
        if (!$this->exists('type_vehicule')) {
            return;
        }

        $typeVehicule = TypeVehicule::normalize($this->input('type_vehicule'));
        $dataToMerge = ['type_vehicule' => $typeVehicule];

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

        $this->merge($dataToMerge);
    }

    public function rules(): array
    {
        $id      = $this->route('id');
        $usineId = $this->input('usine_id') ?? app(UsineContext::class)->getCurrentUsineId();

        return [
            'usine_id'                  => ['sometimes', 'integer', 'exists:usines,id'],
            'nom_vehicule'              => ['sometimes', 'string', 'max:100'],
            'marque'                    => ['sometimes', 'nullable', 'string', 'max:100'],
            'modele'                    => ['sometimes', 'nullable', 'string', 'max:100'],
            'immatriculation'           => [
                'sometimes', 'string', 'max:20',
                Rule::unique('vehicules', 'immatriculation')->where('usine_id', $usineId)->ignore($id),
            ],
            'type_vehicule'             => ['sometimes', Rule::in(TypeVehicule::allowedValues())],
            'capacite_packs'            => ['sometimes', 'integer', 'min:1'],
            'proprietaire_id'           => ['sometimes', 'integer', 'exists:proprietaires,id'],
            'livreur_principal_id'      => ['nullable', 'integer', 'exists:livreurs,id'],
            'pris_en_charge_par_usine'  => ['sometimes', 'boolean'],
            'mode_commission'           => ['sometimes', Rule::in(ModeCommission::values())],
            'valeur_commission'         => ['sometimes', 'numeric', 'min:0'],
            'pourcentage_proprietaire'  => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'pourcentage_livreur'       => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'photo'                     => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'is_active'                 => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (!$this->has('pourcentage_proprietaire') && !$this->has('pourcentage_livreur')) {
                return;
            }
            $prisEnCharge = (bool) $this->input('pris_en_charge_par_usine', false);
            if (!$prisEnCharge) {
                $proprio = (float) $this->input('pourcentage_proprietaire', 0);
                $livreur = (float) $this->input('pourcentage_livreur', 0);
                if (abs(($proprio + $livreur) - 100) > 0.01) {
                    $v->errors()->add(
                        'pourcentage_livreur',
                        'La somme des pourcentages proprietaire + livreur doit etre egale a 100.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'usine_id.exists'        => 'L\'usine sélectionnée n\'existe pas.',
            'immatriculation.unique' => 'Ce numero d\'immatriculation est deja utilise pour cette usine.',
            'type_vehicule.in'       => 'Le type de vehicule doit etre : ' . implode(', ', TypeVehicule::allowedValues()) . '.',
            'mode_commission.in'     => 'Le mode de commission doit etre : ' . implode(', ', ModeCommission::values()) . '.',
            'photo.image'            => 'Le fichier doit etre une image.',
            'photo.mimes'            => 'La photo doit etre au format jpg, jpeg, png ou webp.',
            'photo.max'              => 'La photo ne peut pas depasser 3 Mo.',
        ];
    }
}
