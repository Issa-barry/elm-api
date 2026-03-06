<?php

namespace App\Http\Requests\Forfait;

use Illuminate\Foundation\Http\FormRequest;

class StoreForfaitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug'        => ['required', 'string', 'max:50', 'unique:forfaits,slug'],
            'nom'         => ['required', 'string', 'max:191'],
            'prix'        => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }
}
