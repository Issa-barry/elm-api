<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Parametre;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;

class ProduitStatisticsController extends Controller
{
    use ApiResponse;

    /**
     * Obtenir les statistiques des produits
     */
    public function __invoke()
    {
        try {
            $seuilStockFaible = Parametre::getSeuilStockFaible();

            $stats = [
                'total_produits' => Produit::count(),
                'produits_en_stock' => Produit::where(function ($query) {
                    $query->where('qte_stock', '>', 0)
                        ->orWhere('type', ProduitType::SERVICE);
                })->count(),
                'produits_en_rupture' => Produit::where('qte_stock', '<=', 0)
                    ->where('type', '!=', ProduitType::SERVICE)
                    ->count(),
                'seuil_stock_faible' => $seuilStockFaible,
                // Compte les produits en stock faible en respectant le seuil personnalisé
                // (COALESCE : seuil_alerte_stock si renseigné, sinon paramètre global)
                'produits_stock_faible' => Produit::where('qte_stock', '>', 0)
                    ->where('type', '!=', ProduitType::SERVICE)
                    ->whereRaw('COALESCE(seuil_alerte_stock, ?) > 0', [$seuilStockFaible])
                    ->whereRaw('qte_stock <= COALESCE(seuil_alerte_stock, ?)', [$seuilStockFaible])
                    ->count(),
                'valeur_stock_total' => Produit::sum(DB::raw('prix_vente * qte_stock')),
                'valeur_achat_total' => Produit::sum(DB::raw('prix_achat * qte_stock')),
                'valeur_usine_total' => Produit::sum(DB::raw('prix_usine * qte_stock')),
                'produits_actifs' => Produit::where('statut', 'actif')->count(),
                'produits_inactifs' => Produit::where('statut', 'inactif')->count(),
                'produit_plus_cher' => Produit::orderBy('prix_vente', 'desc')->first(),
                'produit_moins_cher' => Produit::orderBy('prix_vente', 'asc')->first(),
                'stock_total' => Produit::sum('qte_stock'),
                'types' => Produit::select('type', DB::raw('count(*) as count'))
                    ->whereNotNull('type')
                    ->groupBy('type')
                    ->get(),
            ];

            return $this->successResponse($stats, 'Statistiques récupérées avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération des statistiques', $e->getMessage());
        }
    }
}
