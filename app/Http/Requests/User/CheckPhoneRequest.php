<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class CheckPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('phone')) {
            $normalized = preg_replace('/[^0-9+]/', '', (string) $this->input('phone'));
            $this->merge(['phone' => $normalized]);
        }
    }

    public function rules(): array
    {
        return [
            'phone'           => ['required', 'string', 'max:20'],
            'code_phone_pays' => ['required', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required'           => 'Le numéro de téléphone est obligatoire.',
            'phone.string'             => 'Le numéro de téléphone doit être une chaîne de caractères.',
            'phone.max'                => 'Le numéro de téléphone ne peut pas dépasser 20 caractères.',
            'code_phone_pays.required' => 'L\'indicatif pays est obligatoire.',
            'code_phone_pays.string'   => 'L\'indicatif pays doit être une chaîne de caractères.',
            'code_phone_pays.max'      => 'L\'indicatif pays ne peut pas dépasser 10 caractères.',
        ];
    }
}
