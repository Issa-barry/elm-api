<?php

namespace App\Http\Requests\Livreur;

use App\Http\Requests\Concerns\NormalizesInputFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLivreurRequest extends FormRequest
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
        return [
            'nom'       => ['required', 'string', 'max:100'],
            'prenom'    => ['required', 'string', 'max:100'],
            'phone'     => ['required', 'string', 'max:20', Rule::unique('livreurs', 'phone')],
            'email'     => ['nullable', 'email:rfc', 'max:255', Rule::unique('livreurs', 'email')],
            'pays'      => ['nullable', 'string', 'max:100'],
            'ville'     => ['nullable', 'string', 'max:100'],
            'quartier'  => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'    => 'Le nom est obligatoire.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'phone.required'  => 'Le numéro de téléphone est obligatoire.',
            'phone.unique'    => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email'     => 'L\'adresse email doit être valide.',
            'email.unique'    => 'Cette adresse email est déjà utilisée.',
        ];
    }
}
