<?php

namespace App\Http\Controllers\Packing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Packing\StorePackingRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use App\Models\Parametre;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackingStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StorePackingRequest $request)
    {
        try {
            $payload      = $request->safe()->except(['montant']);
            $targetStatut = $payload['statut'] ?? Packing::STATUT_DEFAUT;
            $estActif     = $targetStatut !== Packing::STATUT_ANNULEE;

            if ($estActif) {
                $payload['statut'] = Packing::STATUT_IMPAYEE;
            }

            $packing = DB::transaction(function () use ($payload, $estActif) {
                $packing = Packing::create($payload);

                if ($estActif) {
                    $packing->decrementerStockRouleaux();
                }

                $packing->refresh()->load(['prestataire', 'versements']);

                return $packing;
            });

            $produitRouleau = Parametre::getProduitRouleau()?->fresh();
            $stockAlert     = null;

            if ($produitRouleau) {
                $seuilEffectif = $produitRouleau->low_stock_threshold;
                $isOutOfStock  = $produitRouleau->qte_stock <= 0;
                $isLowStock    = $produitRouleau->is_low_stock;
                $niveauAlerte  = $isOutOfStock ? 'out_of_stock' : ($isLowStock ? 'low_stock' : 'in_stock');

                $message = match ($niveauAlerte) {
                    'out_of_stock' => 'Stock de rouleaux epuise. Reapprovisionnement requis.',
                    'low_stock'    => "Stock de rouleaux faible (seuil: {$seuilEffectif}).",
                    default        => null,
                };

                $stockAlert = [
                    'stock_actuel'       => $produitRouleau->qte_stock,
                    'seuil_stock_faible' => $seuilEffectif,
                    'niveau'             => $niveauAlerte,
                    'is_low_stock'       => $isLowStock,
                    'is_out_of_stock'    => $isOutOfStock,
                    'message'            => $message,
                ];
            }

            return $this->createdResponse([
                'packing'     => $packing,
                'stock_alert' => $stockAlert,
            ], 'Packing cree avec succes');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Les donnees fournies sont invalides.');
        } catch (\Throwable $e) {
            return $this->errorResponse('Erreur lors de la creation du packing', $e->getMessage());
        }
    }
}
