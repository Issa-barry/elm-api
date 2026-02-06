<?php

namespace App\Http\Controllers\Produit;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
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
            $stats = [
                'total_produits' => Produit::count(),
                'produits_en_stock' => Produit::where('in_stock', true)->count(),
                'produits_en_rupture' => Produit::where('in_stock', false)->count(),
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