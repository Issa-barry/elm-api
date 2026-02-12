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
            'code' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('produits', 'code')->ignore($produitId)],
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
            'image_url' => 'nullable|string|max:500',
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
            'code.max' => 'Le code ne peut pas dépasser 100 caractères.',

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
        // Si type service, forcer qte_stock à 0
        if ($this->input('type') === ProduitType::SERVICE->value) {
            $this->merge(['qte_stock' => 0]);
        }
    }
}
