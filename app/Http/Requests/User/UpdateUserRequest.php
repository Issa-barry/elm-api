<?php

namespace App\Http\Requests\User;

use App\Enums\Civilite;
use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use ValidatesKycFields;

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

        if ($normalized !== []) {
            $this->merge($normalized);
        }

        // Nettoyage KYC (trim, '' → null, lowercase piece_type)
        $this->prepareKycFields();
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return array_merge([
            // Identité
            'civilite' => ['sometimes', 'nullable', Rule::in(Civilite::values())],
            'nom' => ['sometimes', 'string', 'max:255'],
            'prenom' => ['sometimes', 'string', 'max:255'],
            'date_naissance' => ['sometimes', 'nullable', 'date', 'before:today'],

            // Contact
            'phone' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email:rfc,dns',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            // Type & rôle
            'type' => ['sometimes', Rule::in(UserType::values())],
            'role' => ['sometimes', 'string', 'exists:roles,name'],

            // Localisation
            'pays' => ['sometimes', 'nullable', 'string', 'max:100'],
            'code_pays' => ['sometimes', 'nullable', 'string', 'max:10'],
            'code_phone_pays' => ['sometimes', 'nullable', 'string', 'max:10'],
            'ville' => ['sometimes', 'nullable', 'string', 'max:100'],
            'quartier' => ['sometimes', 'nullable', 'string', 'max:100'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:500'],

            // Préférences
            'language' => ['sometimes', 'string', 'max:5'],

            // Auth
            'is_active' => ['sometimes', 'boolean'],
        ], $this->kycRules());
    }

    public function messages(): array
    {
        return array_merge([
            'nom.string' => 'Le nom doit être une chaîne de caractères.',
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'prenom.string' => 'Le prénom doit être une chaîne de caractères.',
            'prenom.max' => 'Le prénom ne peut pas dépasser 255 caractères.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser 20 caractères.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'email.max' => 'L\'email ne peut pas dépasser 255 caractères.',
            'type.in' => 'Le type de compte doit être : ' . implode(', ', UserType::values()) . '.',
            'role.exists' => 'Le rôle sélectionné n\'existe pas.',
            'civilite.in' => 'La civilité doit être : ' . implode(', ', Civilite::values()) . '.',
            'date_naissance.date' => 'La date de naissance doit être une date valide.',
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
        ], $this->kycMessages());
    }

    private function normalizeEmail($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : strtolower($normalized);
    }

    private function normalizeLocation($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
    }
}
