<?php

namespace App\Http\Requests\Forfait;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateForfaitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $forfaitId = $this->route('forfait')?->id ?? $this->route('forfait');

        return [
            'slug'        => ['sometimes', 'required', 'string', 'max:50',
                              Rule::unique('forfaits', 'slug')->ignore($forfaitId)],
            'nom'         => ['sometimes', 'required', 'string', 'max:191'],
            'prix'        => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }
}
