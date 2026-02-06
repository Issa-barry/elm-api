<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use Illuminate\Http\Request;

class ProduitIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            $query = Produit::nonArchives()
                ->with(['creator:id,nom,prenom', 'updater:id,nom,prenom']);

            // Filtre par statut
            if ($request->has('statut')) {
                $statut = ProduitStatut::tryFrom($request->statut);
                if ($statut) {
                    $query->deStatut($statut);
                }
            }

            // Filtre par type
            if ($request->has('type')) {
                $type = ProduitType::tryFrom($request->type);
                if ($type) {
                    $query->deType($type);
                }
            }

            // Filtre en stock (calculé)
            if ($request->has('in_stock')) {
                $inStock = $request->boolean('in_stock');
                if ($inStock) {
                    $query->where(function ($q) {
                        $q->where('qte_stock', '>', 0)
                          ->orWhere('type', ProduitType::SERVICE);
                    });
                } else {
                    $query->where('qte_stock', '<=', 0)
                          ->where('type', '!=', ProduitType::SERVICE);
                }
            }

            // Filtre disponibles (actifs + en stock)
            if ($request->boolean('disponibles')) {
                $query->disponibles();
            }

            // Tri
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $allowedSorts = ['nom', 'code', 'prix_vente', 'prix_achat', 'qte_stock', 'created_at', 'updated_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            }

            // Pagination
            if ($request->has('per_page')) {
                $produits = $query->paginate((int) $request->per_page);
            } else {
                $produits = $query->get();
            }

            return $this->successResponse($produits, 'Liste des produits récupérée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des produits', $e->getMessage());
        }
    }
}
