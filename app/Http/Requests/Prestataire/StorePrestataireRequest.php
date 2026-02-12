<?php

namespace App\Http\Requests\Prestataire;

use App\Models\Prestataire;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StorePrestataireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => 'required_unless:type,fournisseur|nullable|string|max:255',
            'prenom' => 'required_unless:type,fournisseur|nullable|string|max:255',
            'raison_sociale' => 'required_if:type,fournisseur|nullable|string|max:255',
            'phone' => 'required|string|max:20|unique:prestataires,phone',
            'email' => 'nullable|email|max:255|unique:prestataires,email',
            'pays' => 'nullable|string|max:100',
            'code_pays' => 'nullable|string|max:5',
            'code_phone_pays' => 'nullable|string|max:5',
            'ville' => 'nullable|string|max:100',
            'quartier' => 'nullable|string|max:100',
            'adresse' => 'nullable|string|max:255',
            'specialite' => 'nullable|string|max:255',
            'type' => ['nullable', Rule::in(array_keys(Prestataire::TYPES))],
            'tarif_horaire' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:5000',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required_unless' => 'Le nom est obligatoire sauf pour les fournisseurs.',
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'prenom.required_unless' => 'Le prénom est obligatoire sauf pour les fournisseurs.',
            'prenom.max' => 'Le prénom ne peut pas dépasser 255 caractères.',
            'raison_sociale.required_if' => 'La raison sociale est obligatoire pour les fournisseurs.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'tarif_horaire.integer' => 'Le tarif horaire doit être un nombre entier.',
            'tarif_horaire.min' => 'Le tarif horaire ne peut pas être négatif.',
            'type.in' => 'Le type doit être : machiniste, mecanicien, consultant ou fournisseur.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Les données fournies sont invalides.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
