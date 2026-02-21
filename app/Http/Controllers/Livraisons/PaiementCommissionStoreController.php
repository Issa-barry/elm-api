<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PaiementCommission;
use App\Models\SortieVehicule;
use Illuminate\Http\Request;

class PaiementCommissionStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $sortieId)
    {
        $sortie = SortieVehicule::with(['factureLivraison', 'deductions', 'paiementCommission'])->find($sortieId);

        if (!$sortie) {
            return $this->notFoundResponse('Sortie véhicule non trouvée');
        }

        if (!$sortie->isCloture()) {
            return $this->errorResponse('Le paiement de commission n\'est possible que pour une sortie clôturée.', null, 422);
        }

        if ($sortie->paiementCommission) {
            return $this->errorResponse('Un paiement de commission existe déjà pour cette sortie.', null, 409);
        }

        // Calcul
        $packsLivres = $sortie->packs_livres;
        $mode        = $sortie->snapshot_mode_commission->value;

        if ($mode === 'forfait') {
            $commissionBrute = $packsLivres * (float) $sortie->snapshot_valeur_commission;
        } else {
            $facture = $sortie->factureLivraison;
            if (!$facture) {
                return $this->errorResponse('Aucune facture liée. Impossible de calculer la commission.', null, 422);
            }
            $commissionBrute = ((float) $facture->montant_brut * (float) $sortie->snapshot_valeur_commission) / 100;
        }

        $pctProprio   = (float) $sortie->snapshot_pourcentage_proprietaire;
        $pctLivreur   = (float) $sortie->snapshot_pourcentage_livreur;
        $partProprioB = $commissionBrute * $pctProprio / 100;
        $partLivreurB = $commissionBrute * $pctLivreur / 100;

        $deducProprio = (float) $sortie->deductions->where('cible', 'proprietaire')->sum('montant');
        $deducLivreur = (float) $sortie->deductions->where('cible', 'livreur')->sum('montant');

        $paiement = PaiementCommission::create([
            'sortie_vehicule_id'      => $sortie->id,
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
