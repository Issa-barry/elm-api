<?php

namespace App\Http\Requests\Usine;

use App\Enums\UsineStatut;
use App\Enums\UsineType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSiege();
    }

    public function rules(): array
    {
        return [
            'nom'          => ['required', 'string', 'max:255'],
            'code'         => ['required', 'string', 'max:50', 'unique:usines,code', 'regex:/^[A-Z0-9_-]+$/'],
            'type'         => ['required', Rule::enum(UsineType::class)],
            'statut'       => ['nullable', Rule::enum(UsineStatut::class)],
            'localisation' => ['nullable', 'string', 'max:255'],
            'pays'         => ['nullable', 'string', 'max:100'],
            'ville'        => ['nullable', 'string', 'max:100'],
            'quartier'     => ['nullable', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'parent_id'    => ['nullable', 'integer', 'exists:usines,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'          => 'Le nom de l\'usine est obligatoire.',
            'code.required'         => 'Le code de l\'usine est obligatoire.',
            'code.unique'           => 'Ce code est déjà utilisé par une autre usine.',
            'code.regex'            => 'Le code ne peut contenir que des lettres majuscules, chiffres, tirets et underscores.',
            'type.required'         => 'Le type d\'usine est obligatoire.',
            'parent_id.exists'      => 'L\'usine parente sélectionnée n\'existe pas.',
        ];
    }

    protected function failedAuthorization(): never
    {
        abort(403, 'Seul le siège peut créer des usines.');
    }
}
