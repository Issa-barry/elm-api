<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use Illuminate\Http\Request;

class ProduitByCodeController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/produits/by-code/{code}?mode=interne|fournisseur|auto
     *
     * Recherche exacte (=) d'un produit par code-barres Code128.
     *
     * Modes :
     *  - interne     : recherche uniquement dans code_interne (1:1)
     *  - fournisseur : recherche uniquement dans code_fournisseur (1:N possible → 409)
     *  - auto        : interne en priorité, puis fournisseur (défaut)
     *
     * Réponses :
     *  200  produit trouvé (unique)
     *  404  aucun produit avec ce code
     *  409  plusieurs produits partagent ce code_fournisseur
     *  422  paramètre mode invalide
     */
    public function __invoke(Request $request, string $code)
    {
        $mode = $request->query('mode', 'auto');

        if (!in_array($mode, ['interne', 'fournisseur', 'auto'], true)) {
            return $this->errorResponse(
                'Le paramètre mode doit être : interne, fournisseur ou auto.',
                null,
                422
            );
        }

        $code = mb_strtoupper(trim($code), 'UTF-8');

        if ($mode === 'interne') {
            return $this->searchByInterne($code);
        }

        if ($mode === 'fournisseur') {
            return $this->searchByFournisseur($code);
        }

        // mode = auto : interne d'abord
        $result = $this->searchByInterne($code, returnNull: true);
        if ($result !== null) {
            return $result;
        }

        return $this->searchByFournisseur($code);
    }

    private function searchByInterne(string $code, bool $returnNull = false): mixed
    {
        $produit = Produit::where('code_interne', $code)->first();

        if (!$produit) {
            if ($returnNull) {
                return null;
            }
            return $this->notFoundResponse('Aucun produit trouvé pour ce code interne.');
        }

        $produit->load(['creator:id,nom,prenom', 'stockCourant', 'produitSiteCourant']);

        return $this->successResponse($produit, 'Produit trouvé.');
    }

    private function searchByFournisseur(string $code): mixed
    {
        $produits = Produit::where('code_fournisseur', $code)->get();

        if ($produits->isEmpty()) {
            return $this->notFoundResponse('Aucun produit trouvé pour ce code fournisseur.');
        }

        if ($produits->count() > 1) {
            return $this->errorResponse(
                'Plusieurs produits partagent ce code fournisseur. Précisez le code interne.',
                ['count' => $produits->count(), 'ids' => $produits->pluck('id')],
                409
            );
        }

        $produit = $produits->first();
        $produit->load(['creator:id,nom,prenom', 'stockCourant', 'produitSiteCourant']);

        return $this->successResponse($produit, 'Produit trouvé.');
    }
}
