<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FactureLivraison;

/**
 * Calcul de la commission depuis une facture de livraison (workflow simplifié).
 * Utilise les snapshots de la facture — jamais les données actuelles du véhicule.
 */
class CommissionFactureCalculController extends Controller
{
    use ApiResponse;

    public function __invoke(int $factureId)
    {
        $facture = FactureLivraison::with('deductions')->find($factureId);

        if (!$facture) {
            return $this->notFoundResponse('Facture non trouvée');
        }

        if (!$facture->snapshot_mode_commission) {
            return $this->errorResponse(
                'Cette facture ne possède pas de snapshots de commission (workflow classique). Utilisez /commissions/{sortieId}/calcul.',
                null,
                422
            );
        }

        $mode = $facture->snapshot_mode_commission;

        if ($mode === 'forfait') {
            $commissionBrute = (int) $facture->packs_charges * (float) $facture->snapshot_valeur_commission;
        } else {
            // pourcentage du montant brut de la facture
            $commissionBrute = ((float) $facture->montant_brut * (float) $facture->snapshot_valeur_commission) / 100;
        }

        $pctProprio   = (float) $facture->snapshot_pourcentage_proprietaire;
        $pctLivreur   = (float) $facture->snapshot_pourcentage_livreur;
        $partProprioB = $commissionBrute * $pctProprio / 100;
        $partLivreurB = $commissionBrute * $pctLivreur / 100;

        $deducProprio = (float) $facture->deductions->where('cible', 'proprietaire')->sum('montant');
        $deducLivreur = (float) $facture->deductions->where('cible', 'livreur')->sum('montant');

        return $this->successResponse([
            'packs_charges'               => $facture->packs_charges,
            'mode_commission'             => $mode,
            'valeur_commission_snapshot'  => (float) $facture->snapshot_valeur_commission,
            'commission_brute_totale'     => round($commissionBrute, 2),
            'part_proprietaire_brute'     => round($partProprioB, 2),
            'part_livreur_brute'          => round($partLivreurB, 2),
            'deductions_proprietaire'     => round($deducProprio, 2),
            'deductions_livreur'          => round($deducLivreur, 2),
            'part_proprietaire_nette'     => round(max(0, $partProprioB - $deducProprio), 2),
            'part_livreur_nette'          => round(max(0, $partLivreurB - $deducLivreur), 2),
        ], 'Calcul de commission effectué');
    }
}
