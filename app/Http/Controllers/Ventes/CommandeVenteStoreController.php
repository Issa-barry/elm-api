<?php

namespace App\Http\Controllers\Ventes;

use App\Enums\StatutCommissionVente;
use App\Enums\StatutFactureVente;
use App\Enums\StatutVersementCommission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vente\StoreCommandeVenteRequest;
use App\Http\Traits\ApiResponse;
use App\Models\CommandeVente;
use App\Models\CommissionVente;
use App\Models\FactureVente;
use App\Models\Produit;
use App\Models\VersementCommission;
use App\Models\Vehicule;
use Illuminate\Support\Facades\DB;

class CommandeVenteStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreCommandeVenteRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            // 1. Préparer les lignes avec snapshots de prix
            $lignesData   = [];
            $produitsQtes = []; // [produit => qte] pour mise à jour stock atomique
            $totalCommande = 0;

            foreach ($validated['lignes'] as $ligne) {
                // lockForUpdate : évite les lectures simultanées du même stock
                $produit = Produit::withoutGlobalScopes()->lockForUpdate()->find($ligne['produit_id']);
                $qte     = (int) $ligne['qte'];
                $prixUsine  = (int) $produit->prix_usine;
                $prixVente  = (int) $produit->prix_vente;
                $totalLigne = $prixVente * $qte;

                $lignesData[] = [
                    'produit_id'          => $produit->id,
                    'qte'                 => $qte,
                    'prix_usine_snapshot' => $prixUsine,
                    'prix_vente_snapshot' => $prixVente,
                    'total_ligne'         => $totalLigne,
                ];

                $produitsQtes[] = ['produit' => $produit, 'qte' => $qte];
                $totalCommande += $totalLigne;
            }

            // 2. Créer la commande
            $commande = CommandeVente::create([
                'vehicule_id'    => $validated['vehicule_id'],
                'total_commande' => $totalCommande,
            ]);

            // 3. Créer les lignes
            foreach ($lignesData as $ligneData) {
                $commande->lignes()->create($ligneData);
            }

            // 3b. Décrémenter le stock de chaque produit
            foreach ($produitsQtes as ['produit' => $produit, 'qte' => $qte]) {
                $produit->ajusterStock(-$qte);
            }

            // 4. Créer automatiquement la facture liée
            FactureVente::create([
                'vehicule_id'       => $commande->vehicule_id,
                'commande_vente_id' => $commande->id,
                'montant_brut'      => $totalCommande,
                'montant_net'       => $totalCommande,
                'statut_facture'    => StatutFactureVente::IMPAYEE->value,
            ]);

            // 5. Calculer et créer la commission
            $vehicule = Vehicule::withoutGlobalScopes()->find($validated['vehicule_id']);

            if ($vehicule && $vehicule->commission_active) {
                $commissionTotale = 0;

                foreach ($lignesData as $ld) {
                    $commissionUnitaire = $ld['prix_vente_snapshot'] - $ld['prix_usine_snapshot'];
                    $commissionTotale  += $commissionUnitaire * $ld['qte'];
                }

                $tauxLivreur      = (float) $vehicule->taux_commission_livreur;
                $partLivreur      = round($commissionTotale * ($tauxLivreur / 100), 2);
                $partProprietaire = round($commissionTotale - $partLivreur, 2);

                // Commission à zéro → on clôture immédiatement, rien à verser
                $statut = ($commissionTotale <= 0)
                    ? StatutCommissionVente::VERSEE->value
                    : StatutCommissionVente::EN_ATTENTE->value;

                $commission = CommissionVente::create([
                    'commande_vente_id'        => $commande->id,
                    'vehicule_id'              => $vehicule->id,
                    'livreur_id'               => $vehicule->livreur_principal_id,
                    'proprietaire_id'          => $vehicule->proprietaire_id,
                    'taux_livreur_snapshot'    => $tauxLivreur,
                    'montant_commission_total' => $commissionTotale,
                    'part_livreur'             => $partLivreur,
                    'part_proprietaire'        => $partProprietaire,
                    'statut'                   => $statut,
                ]);

                if ($commissionTotale > 0) {
                    if ($partLivreur > 0 && $vehicule->livreur_principal_id) {
                        VersementCommission::create([
                            'commission_vente_id' => $commission->id,
                            'beneficiaire_type'   => 'livreur',
                            'beneficiaire_id'     => $vehicule->livreur_principal_id,
                            'montant_attendu'     => $partLivreur,
                            'statut'              => StatutVersementCommission::EN_ATTENTE->value,
                        ]);
                    }

                    if ($partProprietaire > 0 && $vehicule->proprietaire_id) {
                        VersementCommission::create([
                            'commission_vente_id' => $commission->id,
                            'beneficiaire_type'   => 'proprietaire',
                            'beneficiaire_id'     => $vehicule->proprietaire_id,
                            'montant_attendu'     => $partProprietaire,
                            'statut'              => StatutVersementCommission::EN_ATTENTE->value,
                        ]);
                    }
                }
            }

            $commande->load(['vehicule', 'lignes.produit', 'facture', 'commission.versements']);

            return $this->createdResponse($commande, 'Commande créée avec succès');
        });
    }
}
