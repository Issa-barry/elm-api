<?php

namespace App\Http\Requests\Prestataire;

use App\Enums\PrestataireType;
use App\Models\Prestataire;
use App\Services\UsineContext;
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

    protected function prepareForValidation(): void
    {
        $codePays = Prestataire::normalizeIsoCountryCode($this->input('code_pays')) ?? 'GN';
        $codePhonePays = Prestataire::normalizeDialCode($this->input('code_phone_pays')) ?? '+224';

        $this->merge([
            'email' => Prestataire::normalizeEmail($this->input('email')),
            'pays' => Prestataire::normalizeLocation($this->input('pays')) ?? 'Guinee',
            'code_pays' => $codePays,
            'code_phone_pays' => $codePhonePays,
            'phone' => Prestataire::normalizePhoneE164($this->input('phone'), $codePhonePays),
            'ville' => Prestataire::normalizeLocation($this->input('ville')),
            'quartier' => Prestataire::normalizeLocation($this->input('quartier')),
        ]);
    }

    public function rules(): array
    {
        $usineId = app(UsineContext::class)->getCurrentUsineId();

        // unique scopé par usine : un même numéro peut exister dans deux usines différentes
        $phoneUnique = $usineId
            ? Rule::unique('prestataires', 'phone')->where('usine_id', $usineId)
            : Rule::unique('prestataires', 'phone');

        $emailUnique = $usineId
            ? Rule::unique('prestataires', 'email')->where('usine_id', $usineId)
            : Rule::unique('prestataires', 'email');

        return [
            'nom' => ['nullable', 'string', 'max:255', 'required_without:raison_sociale'],
            'prenom' => ['nullable', 'string', 'max:255', 'required_without:raison_sociale'],
            'raison_sociale' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/', $phoneUnique],
            'email' => ['nullable', 'email:rfc,dns', 'max:255', $emailUnique],
            'pays' => ['required', 'string', 'max:100'],
            'code_pays' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'code_phone_pays' => ['required', 'string', 'regex:/^\+[1-9][0-9]{0,3}$/'],
            'ville' => ['nullable', 'string', 'max:100'],
            'quartier' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'specialite' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PrestataireType::class)],
            'tarif_horaire' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $raisonSociale = trim((string) $this->input('raison_sociale', ''));
            $nom = trim((string) $this->input('nom', ''));
            $prenom = trim((string) $this->input('prenom', ''));

            if ($raisonSociale === '' && ($nom === '' || $prenom === '')) {
                if ($nom === '') {
                    $validator->errors()->add('nom', 'Le nom est obligatoire si raison_sociale est vide.');
                }

                if ($prenom === '') {
                    $validator->errors()->add('prenom', 'Le prenom est obligatoire si raison_sociale est vide.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'nom.required_without' => 'Le nom est obligatoire si raison_sociale est vide.',
            'nom.max' => 'Le nom ne peut pas depasser 255 caracteres.',
            'prenom.required_without' => 'Le prenom est obligatoire si raison_sociale est vide.',
            'prenom.max' => 'Le prenom ne peut pas depasser 255 caracteres.',
            'raison_sociale.max' => 'La raison sociale ne peut pas depasser 255 caracteres.',
            'phone.required' => 'Le numero de telephone est obligatoire.',
            'phone.regex' => 'Le numero de telephone doit etre au format E.164 (ex: +224...).',
            'phone.unique' => 'Ce numero de telephone est deja utilise.',
            'email.email' => 'L\'adresse email est invalide.',
            'email.unique' => 'Cette adresse email est deja utilisee.',
            'pays.required' => 'Le pays est obligatoire.',
            'code_pays.required' => 'Le code pays est obligatoire.',
            'code_pays.size' => 'Le code pays doit contenir exactement 2 lettres.',
            'code_pays.regex' => 'Le code pays doit etre au format ISO alpha-2 (ex: GN).',
            'code_phone_pays.required' => 'Le code telephone pays est obligatoire.',
            'code_phone_pays.regex' => 'Le code telephone pays doit etre au format international (ex: +224).',
            'type.required' => 'Le type est obligatoire.',
            'type.enum' => 'Le type doit etre : machiniste, mecanicien, consultant ou fournisseur.',
            'tarif_horaire.integer' => 'Le tarif horaire doit etre un nombre entier.',
            'tarif_horaire.min' => 'Le tarif horaire ne peut pas etre negatif.',
            'is_active.boolean' => 'Le statut actif doit etre un booleen.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Les donnees fournies sont invalides.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
