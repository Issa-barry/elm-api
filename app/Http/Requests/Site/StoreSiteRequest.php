<?php

namespace App\Http\Requests\Site;

use App\Enums\SiteStatut;
use App\Enums\SiteType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSiege();
    }

    public function rules(): array
    {
        return [
            'nom'          => ['required', 'string', 'max:255'],
            'code'         => ['required', 'string', 'max:50', 'unique:sites,code', 'regex:/^[A-Z0-9_-]+$/'],
            'type'         => ['required', Rule::enum(SiteType::class)],
            'statut'       => ['nullable', Rule::enum(SiteStatut::class)],
            'localisation' => ['nullable', 'string', 'max:255'],
            'pays'         => ['nullable', 'string', 'max:100'],
            'ville'        => ['nullable', 'string', 'max:100'],
            'quartier'     => ['nullable', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'parent_id'    => ['nullable', 'integer', 'exists:sites,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'          => 'Le nom du site est obligatoire.',
            'code.required'         => 'Le code du site est obligatoire.',
            'code.unique'           => 'Ce code est déjà utilisé par un autre site.',
            'code.regex'            => 'Le code ne peut contenir que des lettres majuscules, chiffres, tirets et underscores.',
            'type.required'         => 'Le type de site est obligatoire.',
            'parent_id.exists'      => 'Le site parent sélectionné n\'existe pas.',
        ];
    }

    protected function failedAuthorization(): never
    {
        abort(403, 'Seul le siège peut créer des sites.');
    }
}
