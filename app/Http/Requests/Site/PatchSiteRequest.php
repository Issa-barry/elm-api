<?php

namespace App\Http\Requests\Site;

use App\Enums\SiteStatut;
use App\Enums\SiteType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatchSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSiege();
    }

    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'nom'          => ['sometimes', 'string', 'max:255'],
            'code'         => ['sometimes', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('sites', 'code')->ignore($id)],
            'type'         => ['sometimes', Rule::enum(SiteType::class)],
            'statut'       => ['sometimes', Rule::enum(SiteStatut::class)],
            'localisation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pays'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'ville'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'quartier'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'description'  => ['sometimes', 'nullable', 'string', 'max:2000'],
            'parent_id'    => ['sometimes', 'nullable', 'integer', Rule::exists('sites', 'id')->whereNot('id', $id)],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'      => 'Ce code est déjà utilisé par un autre site.',
            'code.regex'       => 'Le code ne peut contenir que des lettres majuscules, chiffres, tirets et underscores.',
            'parent_id.exists' => 'Le site parent sélectionné n\'existe pas.',
        ];
    }

    protected function failedAuthorization(): never
    {
        abort(403, 'Seul le siège peut modifier des sites.');
    }
}
