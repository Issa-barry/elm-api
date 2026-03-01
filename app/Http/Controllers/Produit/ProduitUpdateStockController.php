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
            $produit = Produit::with('stockCourant')->find($id);

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

            $stock = $produit->stockCourant;

            if (!$stock) {
                return $this->notFoundResponse('Stock non trouvé pour cette usine');
            }

            $request->validate([
                'quantite'  => 'required|integer|min:0',
                'operation' => 'nullable|string|in:set,add,subtract',
            ], [
                'quantite.required' => 'La quantite est obligatoire.',
                'quantite.integer'  => 'La quantite doit etre un nombre entier.',
                'quantite.min'      => 'La quantite ne peut pas etre negative.',
                'operation.in'      => 'L\'operation doit etre : set, add ou subtract.',
            ]);

            $operation   = $request->operation ?? 'set';
            $quantite    = (int) $request->quantite;
            $ancienStock = $stock->qte_stock;

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

            $stock->qte_stock = $nouvelleQuantite;
            $stock->save();

            $freshStock    = $stock->fresh();
            $seuilEffectif = $freshStock->low_stock_threshold;
            $isOutOfStock  = $freshStock->qte_stock <= 0;
            $isLowStock    = $freshStock->is_low_stock;
            $niveauAlerte  = $isOutOfStock ? 'out_of_stock' : ($isLowStock ? 'low_stock' : 'in_stock');

            return $this->successResponse([
                'produit'       => $produit->fresh(['stockCourant']),
                'ancien_stock'  => $ancienStock,
                'nouveau_stock' => $freshStock->qte_stock,
                'difference'    => $freshStock->qte_stock - $ancienStock,
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
