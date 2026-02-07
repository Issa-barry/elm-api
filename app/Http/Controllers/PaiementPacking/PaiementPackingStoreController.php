<?php

namespace App\Http\Controllers\PaiementPacking;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaiementPacking\StorePaiementPackingRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Packing;
use App\Models\PaiementPacking;
use Illuminate\Support\Facades\DB;

class PaiementPackingStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StorePaiementPackingRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $validated = $request->validated();

                // Récupérer les packings payables pour ce prestataire et cette période
                $packings = Packing::where('prestataire_id', $validated['prestataire_id'])
                    ->payables()
                    ->where('date_fin', '>=', $validated['periode_debut'])
                    ->where('date_fin', '<=', $validated['periode_fin'])
                    ->get();

                if ($packings->isEmpty()) {
                    return $this->errorResponse(
                        'Aucun packing à payer pour cette période',
                        null,
                        422
                    );
                }

                // Créer le paiement
                $paiement = PaiementPacking::create([
                    'prestataire_id' => $validated['prestataire_id'],
                    'periode_debut' => $validated['periode_debut'],
                    'periode_fin' => $validated['periode_fin'],
                    'montant_total' => $packings->sum('montant'),
                    'nb_packings' => $packings->count(),
                    'date_paiement' => $validated['date_paiement'],
                    'mode_paiement' => $validated['mode_paiement'] ?? PaiementPacking::MODE_ESPECES,
                    'notes' => $validated['notes'] ?? null,
                ]);

                // Associer les packings au paiement
                foreach ($packings as $packing) {
                    $packing->update([
                        'paiement_id' => $paiement->id,
                    ]);
                }

                $paiement->load(['prestataire', 'packings']);

                return $this->createdResponse($paiement, 'Paiement créé avec succès');
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la création du paiement', $e->getMessage());
        }
    }
}
