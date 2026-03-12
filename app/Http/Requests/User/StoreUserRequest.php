<?php

namespace App\Http\Requests\User;

use App\Enums\Civilite;
use App\Enums\UserType;
use App\Http\Requests\Concerns\NormalizesInputFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use NormalizesInputFields, ValidatesKycFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->exists('email')) {
            $normalized['email'] = $this->normalizeEmail($this->input('email'));
        }

        if ($this->exists('ville')) {
            $normalized['ville'] = $this->normalizeLocation($this->input('ville'));
        }

        if ($this->exists('quartier')) {
            $normalized['quartier'] = $this->normalizeLocation($this->input('quartier'));
        }

        if ($this->exists('phone')) {
            $normalized['phone'] = $this->normalizePhone($this->input('phone'));
        }
        if ($this->exists('nom')) {
            $normalized['nom'] = $this->normalizeString($this->input('nom'));
        }
        if ($this->exists('prenom')) {
            $normalized['prenom'] = $this->normalizeString($this->input('prenom'));
        }
        if ($this->exists('pays')) {
            $normalized['pays'] = $this->normalizeLocation($this->input('pays'));
        }
        if ($this->exists('role')) {
            $normalized['role'] = $this->normalizeLowercase($this->input('role'));
        }
        if ($this->exists('type')) {
            $normalized['type'] = $this->normalizeLowercase($this->input('type'));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }

        // Nettoyage KYC (trim, '' → null, lowercase piece_type)
        $this->prepareKycFields();
    }

    public function rules(): array
    {
        return array_merge([
            // Identité
            'civilite' => ['nullable', Rule::in(Civilite::values())],
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date', 'before:today'],

            // Contact
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'email' => ['nullable', 'email:rfc,dns', 'max:255', Rule::unique('users', 'email')],

            // Type & rôle
            'type' => ['required', Rule::in(UserType::values())],
            'role' => ['required', 'string', 'exists:roles,name'],

            // Localisation
            'pays' => ['required', 'string', 'max:100'],
            'code_pays' => ['required', 'string', 'max:10'],
            'code_phone_pays' => ['required', 'string', 'max:10'],
            'ville' => ['required', 'string', 'max:100'],
            'quartier' => ['nullable', 'string', 'max:100'],
            'code_postal' => ['nullable', 'string', 'max:20'],
            'adresse' => ['nullable', 'string', 'max:500'],

            // Organisation (multi-tenant)
            'organisation_id' => ['sometimes', 'nullable', 'integer', 'exists:organisations,id'],

            // Affectation site
            'site_id'   => ['sometimes', 'nullable', 'integer', 'exists:sites,id'],
            'site_role' => ['sometimes', 'nullable', 'string', Rule::in(\App\Enums\SiteRole::values())],

            // Préférences
            'language' => ['sometimes', 'string', 'max:5'],

            // Auth
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
        ], $this->kycRules());
    }

    public function messages(): array
    {
        return array_merge([
            'nom.required' => 'Le nom est obligatoire.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'type.required' => 'Le type de compte est obligatoire.',
            'type.in' => 'Le type de compte doit être : ' . implode(', ', UserType::values()) . '.',
            'role.required' => 'Le rôle est obligatoire.',
            'role.exists' => 'Le rôle sélectionné n\'existe pas.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'is_active.boolean' => 'Le champ actif doit être vrai ou faux.',
            'civilite.in' => 'La civilité doit être : ' . implode(', ', Civilite::values()) . '.',
            'date_naissance.date' => 'La date de naissance doit être une date valide.',
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
        ], $this->kycMessages());
    }

}
