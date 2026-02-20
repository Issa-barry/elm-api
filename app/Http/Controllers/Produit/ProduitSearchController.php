<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use Illuminate\Http\Request;

class ProduitSearchController extends Controller
{
    use ApiResponse;

    /**
     * Rechercher des produits
     */
    public function __invoke(Request $request)
    {
        try {
            $query = Produit::query();

            // Recherche textuelle
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('type', 'like', "%{$search}%");
                });
            }

            // Filtres
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('in_stock')) {
                $query->where('in_stock', $request->boolean('in_stock'));
            }

            // Plage de prix
            if ($request->has('prix_min')) {
                $query->where('prix_vente', '>=', $request->prix_min);
            }

            if ($request->has('prix_max')) {
                $query->where('prix_vente', '<=', $request->prix_max);
            }

            // Plage de stock
            if ($request->has('stock_min')) {
                $query->where('qte_stock', '>=', $request->stock_min);
            }

            if ($request->has('stock_max')) {
                $query->where('qte_stock', '<=', $request->stock_max);
            }

            $produits = $query->orderBy('created_at', 'desc')->get();

            return $this->successResponse([
                'produits' => $produits,
                'count' => $produits->count()
            ], 'Recherche effectuée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la recherche', $e->getMessage());
        }
    }
}