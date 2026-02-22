<?php

namespace App\Http\Requests\Usine;

use App\Enums\UsineStatut;
use App\Enums\UsineType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatchUsineRequest extends FormRequest
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
            'code'         => ['sometimes', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('usines', 'code')->ignore($id)],
            'type'         => ['sometimes', Rule::enum(UsineType::class)],
            'statut'       => ['sometimes', Rule::enum(UsineStatut::class)],
            'localisation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pays'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'ville'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'quartier'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'description'  => ['sometimes', 'nullable', 'string', 'max:2000'],
            'parent_id'    => ['sometimes', 'nullable', 'integer', Rule::exists('usines', 'id')->whereNot('id', $id)],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'      => 'Ce code est déjà utilisé par une autre usine.',
            'code.regex'       => 'Le code ne peut contenir que des lettres majuscules, chiffres, tirets et underscores.',
            'parent_id.exists' => 'L\'usine parente sélectionnée n\'existe pas.',
        ];
    }

    protected function failedAuthorization(): never
    {
        abort(403, 'Seul le siège peut modifier des usines.');
    }
}
