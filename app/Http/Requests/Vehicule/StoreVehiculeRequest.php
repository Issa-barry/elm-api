<?php

namespace App\Http\Requests\Vehicule;

use App\Enums\ModeCommission;
use App\Enums\TypeVehicule;
use App\Services\UsineContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehiculeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $usineId = app(UsineContext::class)->getCurrentUsineId();

        return [
            'nom_vehicule'              => ['required', 'string', 'max:100'],
            'immatriculation'           => [
                'required', 'string', 'max:20',
                Rule::unique('vehicules', 'immatriculation')->where('usine_id', $usineId),
            ],
            'type_vehicule'             => ['required', Rule::in(TypeVehicule::values())],
            'capacite_packs'            => ['required', 'integer', 'min:1'],
            'proprietaire_id'           => ['required', 'integer', 'exists:proprietaires,id'],
            'livreur_principal_id'      => ['nullable', 'integer', 'exists:livreurs,id'],
            'pris_en_charge_par_usine'  => ['sometimes', 'boolean'],
            'mode_commission'           => ['required', Rule::in(ModeCommission::values())],
            'valeur_commission'         => ['required', 'numeric', 'min:0'],
            'pourcentage_proprietaire'  => ['required', 'numeric', 'min:0', 'max:100'],
            'pourcentage_livreur'       => ['required', 'numeric', 'min:0', 'max:100'],
            'photo'                     => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'is_active'                 => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $prisEnCharge = (bool) $this->input('pris_en_charge_par_usine', false);
            if (!$prisEnCharge) {
                $proprio = (float) $this->input('pourcentage_proprietaire', 0);
                $livreur = (float) $this->input('pourcentage_livreur', 0);
                if (abs(($proprio + $livreur) - 100) > 0.01) {
                    $v->errors()->add(
                        'pourcentage_livreur',
                        'La somme des pourcentages propriétaire + livreur doit être égale à 100.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'nom_vehicule.required'    => 'Le nom du véhicule est obligatoire.',
            'immatriculation.required' => 'L\'immatriculation est obligatoire.',
            'immatriculation.unique'   => 'Ce numéro d\'immatriculation est déjà utilisé pour cette usine.',
            'type_vehicule.required'   => 'Le type de véhicule est obligatoire.',
            'type_vehicule.in'         => 'Le type de véhicule doit être : ' . implode(', ', TypeVehicule::values()) . '.',
            'capacite_packs.required'  => 'La capacité en packs est obligatoire.',
            'capacite_packs.min'       => 'La capacité doit être au minimum 1 pack.',
            'proprietaire_id.required' => 'Le propriétaire est obligatoire.',
            'proprietaire_id.exists'   => 'Le propriétaire sélectionné n\'existe pas.',
            'livreur_principal_id.exists' => 'Le livreur sélectionné n\'existe pas.',
            'mode_commission.required' => 'Le mode de commission est obligatoire.',
            'mode_commission.in'       => 'Le mode de commission doit être : ' . implode(', ', ModeCommission::values()) . '.',
            'valeur_commission.required' => 'La valeur de commission est obligatoire.',
            'photo.required'           => 'La photo du véhicule est obligatoire.',
            'photo.image'              => 'Le fichier doit être une image.',
            'photo.mimes'              => 'La photo doit être au format jpg, jpeg, png ou webp.',
            'photo.max'                => 'La photo ne peut pas dépasser 3 Mo.',
        ];
    }
}
