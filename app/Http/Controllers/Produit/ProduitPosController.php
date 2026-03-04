<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use App\Services\SiteContext;
use Illuminate\Http\Request;

/**
 * GET /produits/pos
 * Liste les produits disponibles au POS de l'usine courante :
 *   - statut global = actif
 *   - is_active = true dans produit_usines pour cette usine
 *   - en stock si stockable (services toujours inclus)
 *
 * Retourne aussi les prix effectifs (local si défini, global sinon).
 */
class ProduitPosController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        try {
            $siteId = app(SiteContext::class)->getCurrentSiteId();

            if (!$siteId) {
                return $this->errorResponse(
                    'Aucune usine sélectionnée. Fournissez le header X-Site-Id.',
                    null,
                    400
                );
            }

            $query = Produit::withoutGlobalScopes()
                ->disponiblesPOS($siteId)
                ->with([
                    'stockCourant',
                    'produitSiteCourant',
                ]);

            // Filtre par type
            if ($request->has('type')) {
                $type = \App\Enums\ProduitType::tryFrom($request->type);
                if ($type) {
                    $query->deType($type);
                }
            }

            // Tri
            $sortBy    = $request->get('sort_by', 'nom');
            $sortOrder = $request->get('sort_order', 'asc');
            if (in_array($sortBy, ['nom', 'code', 'created_at'])) {
                $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
            }

            $produits = $request->has('per_page')
                ? $query->paginate((int) $request->per_page)
                : $query->get();

            // Enrichir chaque produit avec ses prix effectifs locaux
            $produits->each(function (Produit $produit) use ($siteId) {
                $produit->setAttribute('prix_effectifs', $produit->prixEffectifDansUsine($siteId));
            });

            return $this->successResponse($produits, 'Catalogue POS récupéré avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération du catalogue POS', $e->getMessage());
        }
    }
}
