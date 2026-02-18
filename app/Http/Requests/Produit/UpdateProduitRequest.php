<?php

namespace App\Http\Requests\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Models\Produit;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateProduitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $produitId = $this->route('id');
        $produit = Produit::find($produitId);

        // Type actuel ou nouveau type
        $type = $this->input('type', $produit?->type?->value ?? ProduitType::MATERIEL->value);

        return [
            'nom' => 'sometimes|required|string|max:255',
            'code' => ['sometimes', 'nullable', 'string', 'size:12', 'regex:/^\d+$/', Rule::unique('produits', 'code')->ignore($produitId)],
            'type' => ['sometimes', Rule::enum(ProduitType::class)],
            'statut' => ['sometimes', Rule::enum(ProduitStatut::class)],

            // Prix en GNF (entiers)
            'prix_usine' => $this->getPrixRules('prix_usine', $type, $produit),
            'prix_vente' => $this->getPrixRules('prix_vente', $type, $produit),
            'prix_achat' => $this->getPrixRules('prix_achat', $type, $produit),

            // Stock
            'qte_stock' => $this->getStockRules($type),
            'cout' => 'nullable|integer|min:0',

            // Optionnels
            'description' => 'nullable|string|max:5000',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    /**
     * Règles de validation pour les prix selon le type
     */
    protected function getPrixRules(string $field, string $type, ?Produit $produit): array|string
    {
        $typeEnum = ProduitType::tryFrom($type);
        if (!$typeEnum) {
            return 'nullable|integer|min:0';
        }

        // Service: achat ou vente (au moins un des deux sera vérifié globalement)
        if ($typeEnum === ProduitType::SERVICE && in_array($field, ['prix_achat', 'prix_vente'], true)) {
            return 'sometimes|nullable|integer|min:0';
        }

        $requiredPrices = $typeEnum->requiredPrices();

        // Si le champ est requis pour ce type ET qu'on change le type OU le champ est présent
        if (in_array($field, $requiredPrices)) {
            // Si on change le type, le prix devient obligatoire
            if ($this->has('type') && $this->input('type') !== $produit?->type?->value) {
                return 'required|integer|min:0';
            }
            // Sinon, si le champ est présent, il doit être valide
            return 'sometimes|nullable|integer|min:0';
        }

        return 'nullable|integer|min:0';
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $produitId = $this->route('id');
            $produit = Produit::find($produitId);

            $typeFinal = $this->input('type', $produit?->type?->value);
            if ($typeFinal !== ProduitType::SERVICE->value) {
                return;
            }

            $prixAchat = $this->has('prix_achat') ? $this->input('prix_achat') : $produit?->prix_achat;
            $prixVente = $this->has('prix_vente') ? $this->input('prix_vente') : $produit?->prix_vente;

            if (($prixAchat === null || $prixAchat === '') && ($prixVente === null || $prixVente === '')) {
                $validator->errors()->add(
                    'prix_achat',
                    'Pour un service, renseignez au moins un prix : achat ou vente.'
                );
            }
        });
    }

    /**
     * Règles de validation pour le stock selon le type
     */
    protected function getStockRules(string $type): array|string
    {
        $typeEnum = ProduitType::tryFrom($type);

        // Service : stock non pertinent
        if ($typeEnum === ProduitType::SERVICE) {
            return 'nullable|integer|min:0';
        }

        return 'sometimes|integer|min:0';
    }

    public function messages(): array
    {
        return [
            // Nom
            'nom.required' => 'Le nom du produit est obligatoire.',
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères.',

            // Code
            'code.unique' => 'Ce code produit existe déjà.',
            'code.size' => 'Le code doit contenir exactement 12 chiffres.',
            'code.regex' => 'Le code produit doit être uniquement numérique.',

            // Type et Statut
            'type.Illuminate\Validation\Rules\Enum' => 'Le type doit être : materiel, service, fabricable ou achat_vente.',
            'statut.Illuminate\Validation\Rules\Enum' => 'Le statut doit être : brouillon, actif, inactif, archive ou rupture_stock.',

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

            // Stock
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

        if ($this->has('type')) {
            $data['type'] = $this->normalizeEnumInput($this->input('type'));
        }

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

        foreach (['prix_usine', 'prix_vente', 'prix_achat', 'qte_stock', 'cout'] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->normalizeIntegerInput($this->input($field));
            }
        }

        $type = $data['type'] ?? $this->input('type');
        if ($type === ProduitType::SERVICE->value) {
            $data['qte_stock'] = 0;
        }

        if (!empty($data)) {
            $this->merge($data);
        }
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
