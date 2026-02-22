<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\SortieVehicule;

class CommissionCalculController extends Controller
{
    use ApiResponse;

    public function __invoke(int $sortieId)
    {
        $sortie = SortieVehicule::with(['factureLivraison', 'deductions'])->find($sortieId);

        if (!$sortie) {
            return $this->notFoundResponse('Sortie véhicule non trouvée');
        }

        $packsLivres = $sortie->packs_livres;
        $mode        = $sortie->snapshot_mode_commission->value;

        if ($mode === 'forfait') {
            $commissionBrute = $packsLivres * (float) $sortie->snapshot_valeur_commission;
        } else {
            // pourcentage du montant brut facture
            $facture = $sortie->factureLivraison;
            if (!$facture) {
                return $this->errorResponse('Aucune facture liée à cette sortie. Impossible de calculer la commission en mode pourcentage.', null, 422);
            }
            $commissionBrute = ((float) $facture->montant_brut * (float) $sortie->snapshot_valeur_commission) / 100;
        }

        $pctProprio  = (float) $sortie->snapshot_pourcentage_proprietaire;
        $pctLivreur  = (float) $sortie->snapshot_pourcentage_livreur;

        $partProprioB = $commissionBrute * $pctProprio / 100;
        $partLivreurB = $commissionBrute * $pctLivreur / 100;

        $deducProprio  = (float) $sortie->deductions->where('cible', 'proprietaire')->sum('montant');
        $deducLivreur  = (float) $sortie->deductions->where('cible', 'livreur')->sum('montant');

        return $this->successResponse([
            'packs_livres'                    => $packsLivres,
            'mode_commission'                 => $mode,
            'valeur_commission_snapshot'      => (float) $sortie->snapshot_valeur_commission,
            'commission_brute_totale'         => round($commissionBrute, 2),
            'part_proprietaire_brute'         => round($partProprioB, 2),
            'part_livreur_brute'              => round($partLivreurB, 2),
            'deductions_proprietaire'         => round($deducProprio, 2),
            'deductions_livreur'              => round($deducLivreur, 2),
            'part_proprietaire_nette'         => round(max(0, $partProprioB - $deducProprio), 2),
            'part_livreur_nette'              => round(max(0, $partLivreurB - $deducLivreur), 2),
        ], 'Calcul de commission effectué');
    }
}
