<?php

namespace App\Services;

use App\Enums\StatutCommissionVente;
use App\Enums\StatutVersementCommission;
use App\Models\CommandeVente;
use App\Models\CommissionVente;
use App\Models\FactureVente;
use App\Models\Vehicule;
use App\Models\VersementCommission;
use Illuminate\Support\Facades\DB;

/**
 * Gestion du cycle de vie des commissions de vente.
 *
 * Règle métier : la commission est créée UNIQUEMENT quand la facture passe à "payee".
 * Elle ne doit jamais être créée à la création de la commande ni de la facture.
 */
class CommissionVenteService
{
    /**
     * Crée (ou fait transitionner) la commission au moment où la facture devient "payee".
     *
     * - Idempotent : si une commission existe déjà, rien n'est fait (eligible_at mis à jour si absent).
     * - Thread-safe : verrou sur la ligne commande_ventes avant toute écriture.
     */
    public function creerSiEligible(FactureVente $facture): void
    {
        if (! $facture->isPayee()) {
            return;
        }

        if (! $facture->commande_vente_id) {
            return;
        }

        DB::transaction(function () use ($facture) {
            // Verrou sur la commande pour sérialiser les appels concurrents
            $commande = CommandeVente::withoutGlobalScopes()
                ->lockForUpdate()
                ->find($facture->commande_vente_id);

            if (! $commande) {
                return;
            }

            // Vérifier et verrouiller toute commission existante pour cette commande
            $commission = CommissionVente::withoutGlobalScopes()
                ->where('commande_vente_id', $commande->id)
                ->lockForUpdate()
                ->first();

            if ($commission) {
                // Commission déjà créée → s'assurer que eligible_at est renseigné
                if (is_null($commission->eligible_at)) {
                    $commission->update(['eligible_at' => now()]);
                }
                // Tout autre statut (partielle, payee, annulee) → rien à faire
                return;
            }

            // ── Aucune commission existante : la créer ────────────────────────

            $vehicule = Vehicule::withoutGlobalScopes()->find($commande->vehicule_id);

            if (! $vehicule || ! $vehicule->commission_active) {
                // Pas de commission → la commande est directement clôturable
                $commande->cloturerSiComplete();
                return;
            }

            // Calculer la commission depuis les snapshots de prix des lignes
            $commissionTotale = 0.0;
            foreach ($commande->lignes as $ligne) {
                $marge             = (float) $ligne->prix_vente_snapshot - (float) $ligne->prix_usine_snapshot;
                $commissionTotale += $marge * (int) $ligne->qte;
            }

            $tauxLivreur      = (float) $vehicule->taux_commission_livreur;
            $partLivreur      = round($commissionTotale * ($tauxLivreur / 100), 2);
            $partProprietaire = round($commissionTotale - $partLivreur, 2);

            if ($commissionTotale <= 0) {
                // Commission nulle → payee immédiatement, aucun versement à créer
                CommissionVente::create([
                    'commande_vente_id'        => $commande->id,
                    'vehicule_id'              => $vehicule->id,
                    'livreur_id'               => $vehicule->livreur_principal_id,
                    'proprietaire_id'          => $vehicule->proprietaire_id,
                    'taux_livreur_snapshot'    => $tauxLivreur,
                    'montant_commission_total' => 0,
                    'part_livreur'             => 0,
                    'part_proprietaire'        => 0,
                    'statut'                   => StatutCommissionVente::PAYEE->value,
                    'eligible_at'              => now(),
                ]);

                // Facture payée + commission payée (0) → commande clôturée
                $commande->cloturerSiComplete();

                return;
            }

            // Commission > 0 → impayee + versements attendus
            $commission = CommissionVente::create([
                'commande_vente_id'        => $commande->id,
                'vehicule_id'              => $vehicule->id,
                'livreur_id'               => $vehicule->livreur_principal_id,
                'proprietaire_id'          => $vehicule->proprietaire_id,
                'taux_livreur_snapshot'    => $tauxLivreur,
                'montant_commission_total' => $commissionTotale,
                'part_livreur'             => $partLivreur,
                'part_proprietaire'        => $partProprietaire,
                'statut'                   => StatutCommissionVente::IMPAYEE->value,
                'eligible_at'              => now(),
            ]);

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
        });
    }
}
