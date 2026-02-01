<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'nom' => ['sometimes', 'string', 'max:255'],
            'prenom' => ['sometimes', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($userId)
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'pays' => ['sometimes', 'nullable', 'string', 'max:100'],
            'code_pays' => ['sometimes', 'nullable', 'string', 'max:10'],
            'code_phone_pays' => ['sometimes', 'nullable', 'string', 'max:10'],
            'ville' => ['sometimes', 'nullable', 'string', 'max:100'],
            'quartier' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.string' => 'Le nom doit être une chaîne de caractères',
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères',
            'prenom.string' => 'Le prénom doit être une chaîne de caractères',
            'prenom.max' => 'Le prénom ne peut pas dépasser 255 caractères',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé',
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser 20 caractères',
            'email.email' => 'L\'adresse email doit être valide',
            'email.unique' => 'Cette adresse email est déjà utilisée',
            'email.max' => 'L\'email ne peut pas dépasser 255 caractères',
        ];
    }
}
