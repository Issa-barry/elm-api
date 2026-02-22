<?php

namespace App\Http\Controllers\Livraisons;

use App\Enums\StatutFactureLivraison;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FactureLivraison;
use App\Models\PaiementCommission;
use Illuminate\Http\Request;

/**
 * Paiement de commission sur une facture de livraison (workflow simplifié).
 *
 * Règles :
 *  - La facture doit être au statut "payee"
 *  - Un seul paiement de commission par facture (409 si doublon)
 *  - Calcul depuis les snapshots de la facture (jamais depuis le véhicule actuel)
 *  - Les déductions liées à la facture sont appliquées avant le net
 */
class PaiementCommissionFactureStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $factureId)
    {
        $facture = FactureLivraison::with(['deductions', 'paiementCommission'])->find($factureId);

        if (!$facture) {
            return $this->notFoundResponse('Facture non trouvée');
        }

        if ($facture->statut_facture !== StatutFactureLivraison::PAYEE) {
            return $this->errorResponse(
                'Le paiement de commission n\'est possible que lorsque la facture est entièrement payée.',
                null,
                422
            );
        }

        if ($facture->paiementCommission) {
            return $this->errorResponse(
                'Un paiement de commission existe déjà pour cette facture.',
                null,
                409
            );
        }

        if (!$facture->snapshot_mode_commission) {
            return $this->errorResponse(
                'Cette facture ne possède pas de snapshots de commission.',
                null,
                422
            );
        }

        // ── Calcul commission ─────────────────────────────────────────────────
        $mode = $facture->snapshot_mode_commission;

        if ($mode === 'forfait') {
            $commissionBrute = (int) $facture->packs_charges * (float) $facture->snapshot_valeur_commission;
        } else {
            $commissionBrute = ((float) $facture->montant_brut * (float) $facture->snapshot_valeur_commission) / 100;
        }

        $pctProprio   = (float) $facture->snapshot_pourcentage_proprietaire;
        $pctLivreur   = (float) $facture->snapshot_pourcentage_livreur;
        $partProprioB = $commissionBrute * $pctProprio / 100;
        $partLivreurB = $commissionBrute * $pctLivreur / 100;

        $deducProprio = (float) $facture->deductions->where('cible', 'proprietaire')->sum('montant');
        $deducLivreur = (float) $facture->deductions->where('cible', 'livreur')->sum('montant');

        $paiement = PaiementCommission::create([
            'facture_livraison_id'    => $facture->id,
            'commission_brute_totale' => round($commissionBrute, 2),
            'part_proprietaire_brute' => round($partProprioB, 2),
            'part_livreur_brute'      => round($partLivreurB, 2),
            'part_proprietaire_nette' => round(max(0, $partProprioB - $deducProprio), 2),
            'part_livreur_nette'      => round(max(0, $partLivreurB - $deducLivreur), 2),
            'date_paiement'           => $request->input('date_paiement', now()->toDateString()),
            'statut'                  => 'paye',
        ]);

        return $this->createdResponse($paiement, 'Commission validée et enregistrée');
    }
}
