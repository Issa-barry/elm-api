<?php

namespace App\Http\Requests\Proprietaire;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProprietaireRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if ($this->exists('phone')) {
            $this->merge(['phone' => preg_replace('/[^0-9+]/', '', (string) $this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nom'      => ['required', 'string', 'max:100'],
            'prenom'   => ['required', 'string', 'max:100'],
            'phone'    => ['required', 'string', 'max:20', Rule::unique('proprietaires', 'phone')],
            'email'    => ['nullable', 'email:rfc', 'max:255', Rule::unique('proprietaires', 'email')],
            'pays'     => ['required', 'string', 'max:100'],
            'ville'    => ['required', 'string', 'max:100'],
            'quartier' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'      => 'Le nom est obligatoire.',
            'prenom.required'   => 'Le prénom est obligatoire.',
            'phone.required'    => 'Le numéro de téléphone est obligatoire.',
            'phone.unique'      => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email'       => 'L\'adresse email doit être valide.',
            'email.unique'      => 'Cette adresse email est déjà utilisée.',
            'pays.required'     => 'Le pays est obligatoire.',
            'ville.required'    => 'La ville est obligatoire.',
            'quartier.required' => 'Le quartier est obligatoire.',
        ];
    }
}
