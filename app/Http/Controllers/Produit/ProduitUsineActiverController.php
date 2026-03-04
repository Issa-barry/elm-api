<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitStatut;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use App\Models\ProduitSite;

/**
 * PATCH /produits/{id}/usines/{usine_id}/activer
 * Activer localement un produit dans une usine.
 */
class ProduitUsineActiverController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id, int $siteId)
    {
        try {
            $produit = Produit::withoutSiteScope()->find($id);

            if (!$produit) {
                return $this->notFoundResponse('Produit non trouvé');
            }

            // Le produit doit être actif globalement pour être activé localement
            if ($produit->statut !== ProduitStatut::ACTIF) {
                return $this->errorResponse(
                    'Le produit doit être actif globalement avant d\'être activé localement.',
                    null,
                    400
                );
            }

            $config = ProduitSite::where('produit_id', $id)
                ->where('site_id', $siteId)
                ->first();

            if (!$config) {
                return $this->notFoundResponse('Ce produit n\'est pas affecté à cette usine');
            }

            if ($config->is_active) {
                return $this->errorResponse('Le produit est déjà actif dans cette usine.', null, 400);
            }

            $config->update(['is_active' => true]);

            return $this->successResponse(
                $config->load('site:id,nom,code'),
                'Produit activé dans l\'usine avec succès'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de l\'activation', $e->getMessage());
        }
    }
}
