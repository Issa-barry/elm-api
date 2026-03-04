<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Produit\UpdateProduitUsinePrixRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use App\Models\ProduitSite;

/**
 * PATCH /produits/{id}/usines/{usine_id}/prix
 * Mettre à jour les prix/coût locaux d'un produit dans une usine.
 * Les champs non envoyés sont conservés (null = prix global utilisé).
 */
class ProduitUsinePrixController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdateProduitUsinePrixRequest $request, int $id, int $siteId)
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

            $data = array_filter($request->validated(), fn ($v) => $v !== null);
            $config->update($data);

            return $this->successResponse([
                'config'          => $config->fresh()->load('site:id,nom,code'),
                'prix_effectifs'  => $produit->prixEffectifDansUsine($siteId),
            ], 'Prix locaux mis à jour avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour des prix', $e->getMessage());
        }
    }
}
