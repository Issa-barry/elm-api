<?php

namespace App\Http\Controllers\FacturePacking;

use App\Http\Controllers\Controller;
use App\Http\Requests\FacturePacking\StoreFacturePackingRequest;
use App\Http\Traits\ApiResponse;
use App\Models\FacturePacking;
use App\Models\Packing;
use Illuminate\Support\Facades\DB;

class FacturePackingStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreFacturePackingRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $validated = $request->validated();

                // Récupérer les packings facturables pour ce prestataire et cette plage de dates
                $packings = Packing::where('prestataire_id', $validated['prestataire_id'])
                    ->facturables()
                    ->whereBetween('date', [$validated['date_debut'], $validated['date_fin']])
                    ->get();

                if ($packings->isEmpty()) {
                    return $this->errorResponse(
                        'Aucun packing à facturer pour cette période',
                        null,
                        422
                    );
                }

                // Créer la facture avec la date du jour
                $facture = FacturePacking::create([
                    'prestataire_id' => $validated['prestataire_id'],
                    'date' => now()->toDateString(),
                    'montant_total' => $packings->sum('montant'),
                    'nb_packings' => $packings->count(),
                    'date_paiement' => $validated['date_paiement'] ?? null,
                    'mode_paiement' => $validated['mode_paiement'] ?? null,
                    'statut' => $validated['statut'] ?? FacturePacking::STATUT_DEFAUT,
                    'notes' => $validated['notes'] ?? null,
                ]);

                // Associer les packings à la facture
                foreach ($packings as $packing) {
                    $packing->update([
                        'facture_id' => $facture->id,
                    ]);
                }

                $facture->load(['prestataire', 'packings']);

                return $this->createdResponse($facture, 'Facture créée avec succès');
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la création de la facture', $e->getMessage());
        }
    }
}
