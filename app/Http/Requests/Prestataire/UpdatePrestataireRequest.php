<?php

namespace App\Http\Requests\Prestataire;

use App\Enums\PrestataireType;
use App\Models\Prestataire;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdatePrestataireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $prestataire = Prestataire::find($this->route('id'));

        $codePhoneInput = $this->input('code_phone_pays');
        $normalizedCodePhone = $codePhoneInput !== null
            ? Prestataire::normalizeDialCode($codePhoneInput)
            : null;

        if ($this->has('email')) {
            $this->merge([
                'email' => Prestataire::normalizeEmail($this->input('email')),
            ]);
        }

        if ($this->has('pays')) {
            $this->merge([
                'pays' => Prestataire::normalizeLocation($this->input('pays')),
            ]);
        }

        if ($this->has('code_pays')) {
            $this->merge([
                'code_pays' => Prestataire::normalizeIsoCountryCode($this->input('code_pays')),
            ]);
        }

        if ($this->has('code_phone_pays')) {
            $this->merge([
                'code_phone_pays' => $normalizedCodePhone,
            ]);
        }

        if ($this->has('phone')) {
            $dialCode = $normalizedCodePhone;
            if ($dialCode === null) {
                $dialCode = Prestataire::normalizeDialCode($this->input('code_phone_pays'))
                    ?? Prestataire::normalizeDialCode($prestataire?->code_phone_pays);
            }

            $this->merge([
                'phone' => Prestataire::normalizePhoneE164($this->input('phone'), $dialCode),
            ]);
        }

        if ($this->has('ville')) {
            $this->merge([
                'ville' => Prestataire::normalizeLocation($this->input('ville')),
            ]);
        }

        if ($this->has('quartier')) {
            $this->merge([
                'quartier' => Prestataire::normalizeLocation($this->input('quartier')),
            ]);
        }
    }

    public function rules(): array
    {
        $prestataireId = (int) $this->route('id');

        return [
            'nom' => ['sometimes', 'nullable', 'string', 'max:255'],
            'prenom' => ['sometimes', 'nullable', 'string', 'max:255'],
            'raison_sociale' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'regex:/^\+[1-9][0-9]{7,14}$/',
                Rule::unique('prestataires', 'phone')->ignore($prestataireId),
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email:rfc,dns',
                'max:255',
                Rule::unique('prestataires', 'email')->ignore($prestataireId),
            ],
            'pays' => ['sometimes', 'required', 'string', 'max:100'],
            'code_pays' => ['sometimes', 'required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'code_phone_pays' => ['sometimes', 'required', 'string', 'regex:/^\+[1-9][0-9]{0,3}$/'],
            'ville' => ['sometimes', 'nullable', 'string', 'max:100'],
            'quartier' => ['sometimes', 'nullable', 'string', 'max:100'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
            'specialite' => ['sometimes', 'nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(PrestataireType::class)],
            'tarif_horaire' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $prestataire = Prestataire::find($this->route('id'));
            if (!$prestataire) {
                return;
            }

            $finalRaisonSociale = trim((string) $this->input('raison_sociale', (string) $prestataire->raison_sociale));
            $finalNom = trim((string) $this->input('nom', (string) $prestataire->nom));
            $finalPrenom = trim((string) $this->input('prenom', (string) $prestataire->prenom));
            $finalPhone = trim((string) $this->input('phone', (string) $prestataire->phone));
            $finalType = $this->input('type', $prestataire->type?->value ?? $prestataire->type);

            if ($finalPhone === '') {
                $validator->errors()->add('phone', 'Le numero de telephone est obligatoire.');
            }

            if (empty($finalType)) {
                $validator->errors()->add('type', 'Le type est obligatoire.');
            }

            if ($finalRaisonSociale === '' && ($finalNom === '' || $finalPrenom === '')) {
                if ($finalNom === '') {
                    $validator->errors()->add('nom', 'Le nom est obligatoire si raison_sociale est vide.');
                }

                if ($finalPrenom === '') {
                    $validator->errors()->add('prenom', 'Le prenom est obligatoire si raison_sociale est vide.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'nom.max' => 'Le nom ne peut pas depasser 255 caracteres.',
            'prenom.max' => 'Le prenom ne peut pas depasser 255 caracteres.',
            'raison_sociale.max' => 'La raison sociale ne peut pas depasser 255 caracteres.',
            'phone.required' => 'Le numero de telephone est obligatoire.',
            'phone.regex' => 'Le numero de telephone doit etre au format E.164 (ex: +224...).',
            'phone.unique' => 'Ce numero de telephone est deja utilise.',
            'email.email' => 'L\'adresse email est invalide.',
            'email.unique' => 'Cette adresse email est deja utilisee.',
            'code_pays.size' => 'Le code pays doit contenir exactement 2 lettres.',
            'code_pays.regex' => 'Le code pays doit etre au format ISO alpha-2 (ex: GN).',
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
