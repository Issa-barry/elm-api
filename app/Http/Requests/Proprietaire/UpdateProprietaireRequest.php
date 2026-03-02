<?php

namespace App\Http\Requests\Proprietaire;

use App\Http\Requests\Concerns\NormalizesInputFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProprietaireRequest extends FormRequest
{
    use NormalizesInputFields;

    public function authorize(): bool { return true; }

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
