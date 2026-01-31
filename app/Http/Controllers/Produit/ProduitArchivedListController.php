<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use Illuminate\Http\Request;

class ProduitArchivedListController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            $query = Produit::archives()
                ->with(['creator:id,nom,prenom', 'archivedByUser:id,nom,prenom']);

            // Filtre par type
            if ($request->has('type')) {
                $type = ProduitType::tryFrom($request->type);
                if ($type) {
                    $query->deType($type);
                }
            }

            // Tri
            $sortBy = $request->get('sort_by', 'archived_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $allowedSorts = ['nom', 'code', 'archived_at', 'created_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
            }

            // Pagination
            if ($request->has('per_page')) {
                $produits = $query->paginate((int) $request->per_page);
            } else {
                $produits = $query->get();
            }

            return $this->successResponse($produits, 'Liste des produits archivés récupérée avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des produits archivés', $e->getMessage());
        }
    }
}
