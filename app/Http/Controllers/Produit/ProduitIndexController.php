<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use App\Services\SiteContext;
use Illuminate\Http\Request;

class ProduitIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            $ctx        = app(SiteContext::class);
            $allSites  = $ctx->isAllSites();
            $stockWith  = $allSites ? 'stocks' : 'stockCourant';

            $query = Produit::nonArchives()
                ->with(['creator:id,nom,prenom', 'updater:id,nom,prenom', $stockWith]);

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

            // Filtre en stock (via table stocks)
            if ($request->has('in_stock')) {
                $inStock = $request->boolean('in_stock');
                if ($allSites) {
                    // Vue consolidée : "en stock" si au moins une usine a qte_stock > 0
                    if ($inStock) {
                        $query->where(function ($q) {
                            $q->where('type', ProduitType::SERVICE)
                              ->orWhereHas('stocks', fn ($sq) => $sq->where('qte_stock', '>', 0));
                        });
                    } else {
                        $query->where('type', '!=', ProduitType::SERVICE)
                              ->whereDoesntHave('stocks', fn ($sq) => $sq->where('qte_stock', '>', 0));
                    }
                } else {
                    if ($inStock) {
                        $query->where(function ($q) {
                            $q->where('type', ProduitType::SERVICE)
                              ->orWhereHas('stockCourant', fn ($sq) => $sq->where('qte_stock', '>', 0));
                        });
                    } else {
                        $query->where('type', '!=', ProduitType::SERVICE)
                              ->whereHas('stockCourant', fn ($sq) => $sq->where('qte_stock', '<=', 0));
                    }
                }
            }

            // Filtre disponibles (actifs + en stock)
            if ($request->boolean('disponibles')) {
                $query->disponibles();
            }

            // Tri (qte_stock retiré : colonne sur stocks, pas produits)
            $sortBy       = $request->get('sort_by', 'created_at');
            $sortOrder    = $request->get('sort_order', 'desc');
            $allowedSorts = ['nom', 'code', 'prix_vente', 'prix_achat', 'created_at', 'updated_at'];
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
