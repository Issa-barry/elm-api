<?php

namespace App\Http\Requests\Prestataire;

use App\Models\Prestataire;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdatePrestataireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $prestataireId = $this->route('id');
        $prestataire = Prestataire::find($prestataireId);
        $currentType = $prestataire?->type;
        $newType = $this->input('type', $currentType);

        $rules = [
            'nom' => ['sometimes', 'nullable', 'string', 'max:255'],
            'prenom' => ['sometimes', 'nullable', 'string', 'max:255'],
            'raison_sociale' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];

        // Si le type final est fournisseur, raison_sociale obligatoire
        if ($newType === Prestataire::TYPE_FOURNISSEUR) {
            // Raison sociale obligatoire si pas déjà en base
            if (empty($prestataire?->raison_sociale) && !$this->has('raison_sociale')) {
                $rules['raison_sociale'] = ['required', 'string', 'max:255'];
            }
        } else {
            // Nom et prénom obligatoires si pas déjà en base
            if (empty($prestataire?->nom) && !$this->has('nom')) {
                $rules['nom'] = ['required', 'string', 'max:255'];
            }
            if (empty($prestataire?->prenom) && !$this->has('prenom')) {
                $rules['prenom'] = ['required', 'string', 'max:255'];
            }
        }

        return array_merge($rules, [
            'phone' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('prestataires', 'phone')->ignore($prestataireId),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('prestataires', 'email')->ignore($prestataireId),
            ],
            'pays' => 'nullable|string|max:100',
            'code_pays' => 'nullable|string|max:5',
            'code_phone_pays' => 'nullable|string|max:5',
            'ville' => 'nullable|string|max:100',
            'quartier' => 'nullable|string|max:100',
            'adresse' => 'nullable|string|max:255',
            'specialite' => 'nullable|string|max:255',
            'type' => ['nullable', Rule::in(array_keys(Prestataire::TYPES))],
            'tarif_horaire' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:5000',
            'is_active' => 'nullable|boolean',
        ]);
    }

    public function messages(): array
    {
        return [
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'prenom.max' => 'Le prénom ne peut pas dépasser 255 caractères.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'tarif_horaire.integer' => 'Le tarif horaire doit être un nombre entier.',
            'tarif_horaire.min' => 'Le tarif horaire ne peut pas être négatif.',
            'type.in' => 'Le type doit être : machiniste, mecanicien, consultant ou fournisseur.',
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
