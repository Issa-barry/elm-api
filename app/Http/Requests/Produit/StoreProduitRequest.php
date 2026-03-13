<?php

namespace App\Http\Requests\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreProduitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type', ProduitType::MATERIEL->value);

        return [
            'nom'  => 'required|string|max:255',
            'code' => 'nullable|string|size:12|regex:/^\\d+$/|unique:produits,code',

            // Codes-barres Code128 (ASCII imprimable 0x21–0x7E, normalisé uppercase)
            // @deprecated code — utiliser code_interne pour les nouveaux clients
            'code_interne'     => ['nullable', 'string', 'max:50',  'regex:/^[\\x21-\\x7E]+$/', 'unique:produits,code_interne'],
            'code_fournisseur' => ['nullable', 'string', 'max:100', 'regex:/^[\\x21-\\x7E]+$/'],

            'type' => ['required', Rule::enum(ProduitType::class)],
            'statut' => ['nullable', Rule::enum(ProduitStatut::class)],

            // Prix en GNF (entiers)
            'prix_usine' => $this->getPrixRules('prix_usine', $type),
            'prix_vente' => $this->getPrixRules('prix_vente', $type),
            'prix_achat' => $this->getPrixRules('prix_achat', $type),

            // Stock
            'qte_stock'          => $this->getStockRules($type),
            'seuil_alerte_stock' => 'nullable|integer|min:0',
            'cout'               => 'nullable|integer|min:0',

            // Optionnels
            'description' => 'nullable|string|max:5000',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'is_global'   => 'nullable|boolean',

            // Affectations usines initiales (is_active non accepté à la création — toujours false)
            'usines'              => 'nullable|array',
            'usines.*.site_id'   => 'required_with:usines|integer|exists:sites,id',
            'usines.*.prix_usine' => 'nullable|integer|min:0',
            'usines.*.prix_achat' => 'nullable|integer|min:0',
            'usines.*.prix_vente' => 'nullable|integer|min:0',
            'usines.*.cout'       => 'nullable|integer|min:0',
            'usines.*.tva'        => 'nullable|integer|min:0|max:100',
        ];
    }

    /**
     * Règles de validation pour les prix selon le type
     */
    protected function getPrixRules(string $field, string $type): array|string
    {
        $typeEnum = ProduitType::tryFrom($type);
        if (!$typeEnum) {
            return 'nullable|integer|min:0';
        }

        // Service: achat ou vente (au moins un des deux sera vérifié globalement)
        if ($typeEnum === ProduitType::SERVICE && in_array($field, ['prix_achat', 'prix_vente'], true)) {
            return 'nullable|integer|min:0';
        }

        $requiredPrices = $typeEnum->requiredPrices();

        if (in_array($field, $requiredPrices)) {
            return 'required|integer|min:0';
        }

        return 'nullable|integer|min:0';
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('type') !== ProduitType::SERVICE->value) {
                return;
            }

            $prixAchat = $this->input('prix_achat');
            $prixVente = $this->input('prix_vente');

            if (($prixAchat === null || $prixAchat === '') && ($prixVente === null || $prixVente === '')) {
                $validator->errors()->add(
                    'prix_achat',
                    'Pour un service, renseignez au moins un prix : achat ou vente.'
                );
            }
        });
    }

    /**
     * Règles de validation pour le stock selon le type.
     * qte_stock est optionnel à la création : le stock initial est toujours 0.
     * La saisie du stock se fait plus tard, par usine, lors de l'activation.
     */
    protected function getStockRules(string $type): string
    {
        return 'nullable|integer|min:0';
    }

    public function messages(): array
    {
        return [
            // Nom
            'nom.required' => 'Le nom du produit est obligatoire.',
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères.',

            // Code legacy
            'code.unique' => 'Ce code produit existe déjà.',

            // Code-barres Code128
            'code_interne.unique'      => 'Ce code interne est déjà utilisé par un autre produit.',
            'code_interne.max'         => 'Le code interne ne peut pas dépasser 50 caractères.',
            'code_interne.regex'       => 'Le code interne ne doit contenir que des caractères imprimables (ASCII 33–126).',
            'code_fournisseur.max'     => 'Le code fournisseur ne peut pas dépasser 100 caractères.',
            'code_fournisseur.regex'   => 'Le code fournisseur ne doit contenir que des caractères imprimables (ASCII 33–126).',
            'code.size' => 'Le code doit contenir exactement 12 chiffres.',
            'code.regex' => 'Le code produit doit être uniquement numérique.',

            // Type et Statut
            'type.required' => 'Le type de produit est obligatoire.',
            'type.Illuminate\Validation\Rules\Enum' => 'Le type doit être : materiel, service, fabricable ou achat_vente.',
            'statut.Illuminate\Validation\Rules\Enum' => 'Le statut doit être : brouillon, actif, inactif ou archive.',

            // Prix
            'prix_usine.required' => 'Le prix usine est obligatoire pour ce type de produit.',
            'prix_usine.integer' => 'Le prix usine doit être un nombre entier (GNF).',
            'prix_usine.min' => 'Le prix usine ne peut pas être négatif.',

            'prix_vente.required' => 'Le prix de vente est obligatoire pour ce type de produit.',
            'prix_vente.integer' => 'Le prix de vente doit être un nombre entier (GNF).',
            'prix_vente.min' => 'Le prix de vente ne peut pas être négatif.',

            'prix_achat.required' => 'Le prix d\'achat est obligatoire pour ce type de produit.',
            'prix_achat.integer' => 'Le prix d\'achat doit être un nombre entier (GNF).',
            'prix_achat.min' => 'Le prix d\'achat ne peut pas être négatif.',

            // Stock (optionnel à la création — sera saisi lors de l'activation par usine)
            'qte_stock.integer' => 'La quantité doit être un nombre entier.',
            'qte_stock.min' => 'La quantité ne peut pas être négative.',

            // Coût
            'cout.integer' => 'Le coût doit être un nombre entier (GNF).',
            'cout.min' => 'Le coût ne peut pas être négatif.',

            // Description
            'description.max' => 'La description ne peut pas dépasser 5000 caractères.',

            // Image
            'image.image' => 'Le fichier doit être une image.',
            'image.mimes' => 'L\'image doit être au format jpg, jpeg, png ou webp.',
            'image.max' => 'L\'image ne doit pas dépasser 5 Mo.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nom' => 'nom du produit',
            'code' => 'code produit',
            'type' => 'type de produit',
            'statut' => 'statut',
            'prix_usine' => 'prix usine',
            'prix_vente' => 'prix de vente',
            'prix_achat' => 'prix d\'achat',
            'qte_stock' => 'quantité en stock',
            'cout' => 'coût',
            'description' => 'description',
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

    /**
     * Prépare les données avant validation
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        $type = $this->has('type')
            ? $this->normalizeEnumInput($this->input('type'))
            : ProduitType::MATERIEL->value;
        $data['type'] = $type;

        if ($this->has('statut')) {
            $data['statut'] = $this->normalizeEnumInput($this->input('statut'));
        }

        if ($this->has('nom')) {
            $data['nom'] = $this->normalizeTextInput($this->input('nom'));
        }

        if ($this->has('code')) {
            $data['code'] = $this->normalizeCodeInput($this->input('code'));
        }

        if ($this->has('description')) {
            $data['description'] = $this->normalizeTextInput($this->input('description'));
        }

        foreach (['prix_usine', 'prix_vente', 'prix_achat', 'qte_stock', 'seuil_alerte_stock', 'cout'] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->normalizeIntegerInput($this->input($field));
            }
        }

        $this->merge($data);
    }

    private function normalizeTextInput($value, bool $collapseSpaces = true): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($collapseSpaces) {
            $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        }

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeCodeInput($value): ?string
    {
        $normalized = $this->normalizeTextInput($value, false);

        if ($normalized === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', '', $normalized) ?? $normalized;

        if ($normalized === '') {
            return null;
        }

        return mb_strtoupper($normalized, 'UTF-8');
    }

    private function normalizeEnumInput($value): ?string
    {
        $normalized = $this->normalizeTextInput($value, false);

        if ($normalized === null) {
            return null;
        }

        return strtolower($normalized);
    }

    private function normalizeIntegerInput($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            // Retirer les separateurs d'espaces (y compris espace inseparable).
            $normalized = str_replace([' ', "\u{00A0}"], '', $normalized);

            // Retirer les virgules uniquement si elles sont des separateurs de milliers.
            if (str_contains($normalized, ',')) {
                if (preg_match('/^-?\d{1,3}(,\d{3})+$/', $normalized)) {
                    $normalized = str_replace(',', '', $normalized);
                } else {
                    return $value;
                }
            }

            if (preg_match('/^-?\d+$/', $normalized)) {
                return (int) $normalized;
            }
        }

        return $value;
    }
}
