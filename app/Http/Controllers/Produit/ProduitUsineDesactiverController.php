<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use App\Models\ProduitSite;

/**
 * PATCH /produits/{id}/usines/{usine_id}/desactiver
 * Désactiver localement un produit dans une usine.
 */
class ProduitUsineDesactiverController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id, int $siteId)
    {
        try {
            $produit = Produit::withoutSiteScope()->find($id);

            if (!$produit) {
                return $this->notFoundResponse('Produit non trouvé');
            }

            $config = ProduitSite::where('produit_id', $id)
                ->where('site_id', $siteId)
                ->first();

            if (!$config) {
                return $this->notFoundResponse('Ce produit n\'est pas affecté à cette usine');
            }

            if (!$config->is_active) {
                return $this->errorResponse('Le produit est déjà inactif dans cette usine.', null, 400);
            }

            $config->update(['is_active' => false]);

            return $this->successResponse(
                $config->load('site:id,nom,code'),
                'Produit désactivé dans l\'usine avec succès'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la désactivation', $e->getMessage());
        }
    }
}
