<?php

namespace App\Http\Controllers\Ventes;

use App\Enums\StatutCommissionVente;
use App\Enums\StatutFactureVente;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vente\StoreEncaissementVenteRequest;
use App\Http\Traits\ApiResponse;
use App\Models\CommissionVente;
use App\Models\EncaissementVente;
use App\Models\FactureVente;

class EncaissementVenteStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreEncaissementVenteRequest $request)
    {
        $facture = FactureVente::find($request->validated('facture_vente_id'));

        if (!$facture) {
            return $this->notFoundResponse('Facture non trouvée');
        }

        // Bloquer les encaissements si la facture est annulée
        if ($facture->isAnnulee()) {
            return $this->errorResponse(
                'Impossible d\'encaisser sur une facture annulée.',
                null,
                422
            );
        }

        // Contrôle : encaissements cumulés <= montant_net
        $dejaEncaisse = $facture->encaissements()->sum('montant');
        $nouveau      = (float) $request->validated('montant');
        $total        = $dejaEncaisse + $nouveau;

        if ($total > (float) $facture->montant_net) {
            return $this->validationErrorResponse(
                ['montant' => [
                    "Le cumul des encaissements ({$total}) dépasserait le montant net de la facture ({$facture->montant_net})."
                ]],
                'Dépassement du montant facturé'
            );
        }

        $encaissement = EncaissementVente::create($request->validated());

        // Mise à jour automatique du statut facture
        $facture->recalculStatut();

        // Déclencher l'éligibilité de la commission si la facture est maintenant payée
        if ($facture->fresh()->isPayee() && $facture->commande_vente_id) {
            $commission = CommissionVente::where('commande_vente_id', $facture->commande_vente_id)
                ->where('statut', StatutCommissionVente::EN_ATTENTE->value)
                ->first();

            if ($commission) {
                $commission->update([
                    'statut'      => StatutCommissionVente::ELIGIBLE->value,
                    'eligible_at' => now(),
                ]);
            }
        }

        return $this->createdResponse($encaissement->load('facture'), 'Encaissement enregistré');
    }
}
