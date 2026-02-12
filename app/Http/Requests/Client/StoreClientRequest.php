<?php

namespace App\Http\Requests\Client;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'raison_sociale' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20|unique:clients,phone',
            'email' => 'nullable|email|max:255|unique:clients,email',
            'pays' => 'nullable|string|max:100',
            'code_pays' => 'nullable|string|max:5',
            'code_phone_pays' => 'nullable|string|max:5',
            'ville' => 'nullable|string|max:100',
            'quartier' => 'nullable|string|max:100',
            'adresse' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire.',
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'prenom.max' => 'Le prénom ne peut pas dépasser 255 caractères.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
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
