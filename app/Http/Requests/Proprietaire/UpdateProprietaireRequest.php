<?php

namespace App\Http\Requests\Proprietaire;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProprietaireRequest extends FormRequest
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
        $id = $this->route('id');

        return [
            'nom'      => ['sometimes', 'string', 'max:100'],
            'prenom'   => ['sometimes', 'string', 'max:100'],
            'phone'    => ['sometimes', 'string', 'max:20', Rule::unique('proprietaires', 'phone')->ignore($id)],
            'email'    => ['nullable', 'email:rfc', 'max:255', Rule::unique('proprietaires', 'email')->ignore($id)],
            'pays'     => ['sometimes', 'string', 'max:100'],
            'ville'    => ['sometimes', 'string', 'max:100'],
            'quartier' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email'  => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
        ];
    }
}
