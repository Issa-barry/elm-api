<?php

namespace App\Http\Controllers\Packing;

use App\Enums\PackingStatut;
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
            $payload = $request->safe()->except(['montant']);
            $targetStatut = $payload['statut'] ?? Packing::STATUT_DEFAUT;
            $doImmediateValidation = $targetStatut === PackingStatut::VALIDE->value;

            if ($doImmediateValidation) {
                $payload['statut'] = PackingStatut::A_VALIDER->value;
            }

            $result = DB::transaction(function () use ($payload, $doImmediateValidation) {
                $packing = Packing::create($payload);
                $facture = null;

                if ($doImmediateValidation) {
                    $facture = $packing->valider();
                }

                $packing->refresh()->load(['prestataire', 'facture']);

                return [
                    'packing' => $packing,
                    'facture' => $facture?->fresh(['prestataire', 'packings']) ?? $packing->facture,
                ];
            });

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
                'packing' => $result['packing'],
                'facture' => $result['facture'],
                'stock_alert' => $stockAlert,
            ], $doImmediateValidation
                ? 'Packing cree, valide et facture generee avec succes'
                : 'Packing cree avec succes');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Les donnees fournies sont invalides.');
        } catch (\Throwable $e) {
            return $this->errorResponse('Erreur lors de la creation du packing', $e->getMessage());
        }
    }
}
