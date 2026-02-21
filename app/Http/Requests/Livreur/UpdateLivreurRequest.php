<?php

namespace App\Http\Requests\Livreur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLivreurRequest extends FormRequest
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
            'phone'    => ['sometimes', 'string', 'max:20', Rule::unique('livreurs', 'phone')->ignore($id)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
        ];
    }
}
