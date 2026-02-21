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
                return $this->notFoundResponse('Produit non trouve');
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
                'quantite' => 'required|integer|min:0',
                'operation' => 'nullable|string|in:set,add,subtract',
            ], [
                'quantite.required' => 'La quantite est obligatoire.',
                'quantite.integer' => 'La quantite doit etre un nombre entier.',
                'quantite.min' => 'La quantite ne peut pas etre negative.',
                'operation.in' => 'L\'operation doit etre : set, add ou subtract.',
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

            $fresh           = $produit->fresh();
            $seuilEffectif   = $fresh->low_stock_threshold;
            $isOutOfStock    = $fresh->qte_stock <= 0;
            $isLowStock      = $fresh->is_low_stock;
            $niveauAlerte    = $isOutOfStock ? 'out_of_stock' : ($isLowStock ? 'low_stock' : 'in_stock');

            return $this->successResponse([
                'produit'       => $fresh,
                'ancien_stock'  => $ancienStock,
                'nouveau_stock' => $fresh->qte_stock,
                'difference'    => $fresh->qte_stock - $ancienStock,
                'ancien_statut' => $ancienStatut->value,
                'nouveau_statut'=> $fresh->statut->value,
                'stock_alert'   => [
                    'seuil_stock_faible' => $seuilEffectif,
                    'niveau'             => $niveauAlerte,
                    'is_low_stock'       => $isLowStock,
                    'is_out_of_stock'    => $isOutOfStock,
                    'message'            => match ($niveauAlerte) {
                        'out_of_stock' => 'Stock epuise. Reapprovisionnement requis.',
                        'low_stock'    => "Stock faible (seuil: {$seuilEffectif}).",
                        default        => null,
                    },
                ],
            ], 'Stock mis a jour avec succes');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise a jour du stock', $e->getMessage());
        }
    }
}
