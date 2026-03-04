<?php

namespace App\Http\Requests\Organisation;

use App\Enums\OrganisationStatut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganisationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Contrôlé par le middleware role:super_admin sur la route
    }

    public function rules(): array
    {
        return [
            'nom'         => ['required', 'string', 'max:191'],
            'code'        => ['required', 'string', 'max:50', 'unique:organisations,code'],
            'email'       => ['nullable', 'email', 'max:191'],
            'phone'       => ['nullable', 'string', 'max:50'],
            'pays'        => ['nullable', 'string', 'max:100'],
            'ville'       => ['nullable', 'string', 'max:100'],
            'quartier'    => ['nullable', 'string', 'max:100'],
            'adresse'     => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'statut'      => ['nullable', Rule::enum(OrganisationStatut::class)],
        ];
    }
}
