<?php

namespace App\Http\Requests\Vente;

use App\Enums\ProduitType;
use App\Models\Produit;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommandeVenteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'vehicule_id'         => ['required', 'integer', 'exists:vehicules,id'],
            'lignes'              => ['required', 'array', 'min:1'],
            'lignes.*.produit_id' => ['required', 'integer', 'exists:produits,id'],
            'lignes.*.qte'        => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $lignes = $this->input('lignes', []);

            foreach ($lignes as $index => $ligne) {
                $produitId = $ligne['produit_id'] ?? null;
                if (! $produitId) {
                    continue;
                }

                $produit = Produit::withoutGlobalScopes()->find($produitId);
                if ($produit && $produit->type !== ProduitType::FABRICABLE) {
                    $v->errors()->add(
                        "lignes.{$index}.produit_id",
                        "Le produit \"{$produit->nom}\" n'est pas de type fabricable."
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'vehicule_id.required'         => 'Le véhicule est obligatoire.',
            'vehicule_id.exists'           => 'Le véhicule sélectionné n\'existe pas.',
            'lignes.required'              => 'La commande doit contenir au moins une ligne.',
            'lignes.min'                   => 'La commande doit contenir au moins une ligne.',
            'lignes.*.produit_id.required' => 'Le produit est obligatoire pour chaque ligne.',
            'lignes.*.produit_id.exists'   => 'Le produit sélectionné n\'existe pas.',
            'lignes.*.qte.required'        => 'La quantité est obligatoire pour chaque ligne.',
            'lignes.*.qte.min'             => 'La quantité doit être au minimum 1.',
        ];
    }
}
