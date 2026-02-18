<?php

namespace App\Http\Requests\User;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
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
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'email' => ['nullable', 'email:rfc,dns', 'max:255', Rule::unique('users', 'email')],
            'type' => ['required', Rule::in(UserType::values())],
            'pays' => ['required', 'string', 'max:100'],
            'code_pays' => ['required', 'string', 'max:10'],
            'code_phone_pays' => ['required', 'string', 'max:10'],
            'ville' => ['required', 'string', 'max:100'],
            'quartier' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'type.required' => 'Le type de compte est obligatoire.',
            'type.in' => 'Le type de compte doit être : ' . implode(', ', UserType::values()) . '.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'is_active.boolean' => 'Le champ actif doit être vrai ou faux.',
        ];
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
