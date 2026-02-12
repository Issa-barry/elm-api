<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use Illuminate\Http\Request;

class ProduitUpdateStockController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, $id)
    {
        try {
            $produit = Produit::find($id);

            if (!$produit) {
                return $this->notFoundResponse('Produit non trouvé');
            }

            // Service : pas de stock
            if ($produit->type === ProduitType::SERVICE) {
                return $this->errorResponse(
                    'Impossible de modifier le stock d\'un service.',
                    null,
                    400
                );
            }

            $request->validate([
                'quantite' => 'required|integer',
                'operation' => 'nullable|string|in:set,add,subtract',
            ], [
                'quantite.required' => 'La quantité est obligatoire.',
                'quantite.integer' => 'La quantité doit être un nombre entier.',
                'operation.in' => 'L\'opération doit être : set, add ou subtract.',
            ]);

            $operation = $request->operation ?? 'set';
            $quantite = (int) $request->quantite;
            $ancienStock = $produit->qte_stock;
            $ancienStatut = $produit->statut;

            switch ($operation) {
                case 'add':
                    $nouvelleQuantite = $ancienStock + $quantite;
                    break;
                case 'subtract':
                    $nouvelleQuantite = max(0, $ancienStock - $quantite);
                    break;
                default:
                    $nouvelleQuantite = max(0, $quantite);
            }

            $produit->qte_stock = $nouvelleQuantite;
            $produit->save();

            return $this->successResponse([
                'produit' => $produit->fresh(),
                'ancien_stock' => $ancienStock,
                'nouveau_stock' => $produit->qte_stock,
                'difference' => $produit->qte_stock - $ancienStock,
                'ancien_statut' => $ancienStatut->value,
                'nouveau_statut' => $produit->statut->value,
            ], 'Stock mis à jour avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour du stock', $e->getMessage());
        }
    }
}
