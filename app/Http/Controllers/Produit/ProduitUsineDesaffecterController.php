<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use App\Models\ProduitUsine;

/**
 * DELETE /produits/{id}/usines/{usine_id}
 * Désaffecter un produit d'une usine (supprime la config locale — le stock est conservé).
 */
class ProduitUsineDesaffecterController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id, int $usineId)
    {
        try {
            $produit = Produit::withoutUsineScope()->find($id);

            if (!$produit) {
                return $this->notFoundResponse('Produit non trouvé');
            }

            $config = ProduitUsine::where('produit_id', $id)
                ->where('usine_id', $usineId)
                ->first();

            if (!$config) {
                return $this->notFoundResponse('Ce produit n\'est pas affecté à cette usine');
            }

            $config->delete();

            return $this->successResponse(null, 'Produit désaffecté de l\'usine avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la désaffectation', $e->getMessage());
        }
    }
}
