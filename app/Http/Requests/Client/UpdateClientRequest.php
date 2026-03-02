<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Concerns\NormalizesInputFields;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    use NormalizesInputFields;

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
        if ($this->exists('phone')) {
            $normalized['phone'] = $this->normalizePhone($this->input('phone'));
        }
        if ($this->exists('nom')) {
            $normalized['nom'] = $this->normalizeString($this->input('nom'));
        }
        if ($this->exists('prenom')) {
            $normalized['prenom'] = $this->normalizeString($this->input('prenom'));
        }
        if ($this->exists('raison_sociale')) {
            $normalized['raison_sociale'] = $this->normalizeString($this->input('raison_sociale'));
        }
        if ($this->exists('pays')) {
            $normalized['pays'] = $this->normalizeLocation($this->input('pays'));
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
        $clientId = $this->route('id');

        return [
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'raison_sociale' => 'nullable|string|max:255',
            'phone' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('clients', 'phone')->ignore($clientId),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('clients', 'email')->ignore($clientId),
            ],
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
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'prenom.max' => 'Le prénom ne peut pas dépasser 255 caractères.',
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
