<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produit\AffecterProduitUsineRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Produit;
use App\Models\ProduitSite;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

/**
 * POST /produits/{id}/usines
 * Affecter un produit global à une usine (crée la config locale + stock si nécessaire).
 */
class ProduitUsineAffecterController extends Controller
{
    use ApiResponse;

    public function __invoke(AffecterProduitUsineRequest $request, int $id)
    {
        try {
            $produit = Produit::withoutSiteScope()->find($id);

            if (!$produit) {
                return $this->notFoundResponse('Produit non trouvé');
            }

            $data    = $request->validated();
            $siteId = (int) $data['site_id'];

            // Vérifier si l'affectation existe déjà
            if (ProduitSite::where('produit_id', $id)->where('site_id', $siteId)->exists()) {
                return $this->errorResponse(
                    'Ce produit est déjà affecté à cette usine.',
                    null,
                    409
                );
            }

            return DB::transaction(function () use ($produit, $siteId, $data) {
                $config = ProduitSite::create([
                    'produit_id' => $produit->id,
                    'site_id'   => $siteId,
                    'is_active'  => $data['is_active']  ?? false,
                    'prix_usine' => $data['prix_usine'] ?? null,
                    'prix_achat' => $data['prix_achat'] ?? null,
                    'prix_vente' => $data['prix_vente'] ?? null,
                    'cout'       => $data['cout']       ?? null,
                    'tva'        => $data['tva']        ?? null,
                ]);

                // Créer une entrée stock si le produit est stockable
                if ($produit->type !== ProduitType::SERVICE) {
                    Stock::firstOrCreate(
                        ['produit_id' => $produit->id, 'site_id' => $siteId],
                        ['qte_stock' => 0]
                    );
                }

                return $this->createdResponse(
                    $config->load('site:id,nom,code'),
                    'Produit affecté à l\'usine avec succès'
                );
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de l\'affectation', $e->getMessage());
        }
    }
}
