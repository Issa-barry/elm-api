<?php

namespace App\Http\Controllers\Produit;

use App\Enums\ProduitType;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Parametre;
use App\Models\Produit;
use App\Services\UsineContext;
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
            $usineId          = app(UsineContext::class)->getCurrentUsineId();

            $stats = [
                'total_produits'      => Produit::count(),
                'produits_en_stock'   => Produit::where(function ($q) use ($usineId) {
                    $q->where('type', ProduitType::SERVICE)
                      ->orWhereHas('stocks', fn ($sq) =>
                          $sq->where('usine_id', $usineId)->where('qte_stock', '>', 0)
                      );
                })->count(),
                'produits_en_rupture' => Produit::where('type', '!=', ProduitType::SERVICE)
                    ->whereHas('stocks', fn ($sq) =>
                        $sq->where('usine_id', $usineId)->where('qte_stock', '<=', 0)
                    )->count(),
                'seuil_stock_faible'  => $seuilStockFaible,
                'produits_stock_faible' => Produit::where('type', '!=', ProduitType::SERVICE)
                    ->whereHas('stocks', function ($sq) use ($usineId, $seuilStockFaible) {
                        $sq->where('usine_id', $usineId)
                           ->where('qte_stock', '>', 0)
                           ->whereRaw('COALESCE(seuil_alerte_stock, ?) > 0', [$seuilStockFaible])
                           ->whereRaw('qte_stock <= COALESCE(seuil_alerte_stock, ?)', [$seuilStockFaible]);
                    })->count(),
                'valeur_stock_total'  => DB::table('produits')
                    ->join('stocks', function ($join) use ($usineId) {
                        $join->on('stocks.produit_id', '=', 'produits.id')
                             ->where('stocks.usine_id', $usineId);
                    })
                    ->whereNull('produits.deleted_at')
                    ->sum(DB::raw('produits.prix_vente * stocks.qte_stock')),
                'valeur_achat_total'  => DB::table('produits')
                    ->join('stocks', function ($join) use ($usineId) {
                        $join->on('stocks.produit_id', '=', 'produits.id')
                             ->where('stocks.usine_id', $usineId);
                    })
                    ->whereNull('produits.deleted_at')
                    ->sum(DB::raw('produits.prix_achat * stocks.qte_stock')),
                'valeur_usine_total'  => DB::table('produits')
                    ->join('stocks', function ($join) use ($usineId) {
                        $join->on('stocks.produit_id', '=', 'produits.id')
                             ->where('stocks.usine_id', $usineId);
                    })
                    ->whereNull('produits.deleted_at')
                    ->sum(DB::raw('produits.prix_usine * stocks.qte_stock')),
                'produits_actifs'     => Produit::where('statut', 'actif')->count(),
                'produits_inactifs'   => Produit::where('statut', 'inactif')->count(),
                'produit_plus_cher'   => Produit::orderBy('prix_vente', 'desc')->first(),
                'produit_moins_cher'  => Produit::orderBy('prix_vente', 'asc')->first(),
                'stock_total'         => DB::table('stocks')
                    ->where('usine_id', $usineId)
                    ->sum('qte_stock'),
                'types'               => Produit::select('type', DB::raw('count(*) as count'))
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
