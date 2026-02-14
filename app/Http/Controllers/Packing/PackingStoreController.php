<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Packing\StorePackingRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use App\Models\Parametre;

class PackingStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StorePackingRequest $request)
    {
        try {
            $packing = Packing::create($request->validated());
            $packing->load(['prestataire', 'facture']);
            $produitRouleau = Parametre::getProduitRouleau()?->fresh();

            $stockAlert = null;
            if ($produitRouleau) {
                $seuilStockFaible = Parametre::getSeuilStockFaible();
                $niveauAlerte = Parametre::getNiveauAlerteStock($produitRouleau->qte_stock);

                $message = match ($niveauAlerte) {
                    'out_of_stock' => 'Stock de rouleaux epuise. Reapprovisionnement requis.',
                    'low_stock' => "Stock de rouleaux faible (seuil: {$seuilStockFaible}).",
                    default => null,
                };

                $stockAlert = [
                    'stock_actuel' => $produitRouleau->qte_stock,
                    'seuil_stock_faible' => $seuilStockFaible,
                    'niveau' => $niveauAlerte,
                    'is_low_stock' => Parametre::isStockFaible($produitRouleau->qte_stock),
                    'is_out_of_stock' => $produitRouleau->qte_stock <= 0,
                    'message' => $message,
                ];
            }

            return $this->createdResponse([
                'packing' => $packing,
                'facture' => $packing->facture,
                'stock_alert' => $stockAlert,
            ], 'Packing cree et facture generee avec succes');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la creation du packing', $e->getMessage());
        }
    }
}