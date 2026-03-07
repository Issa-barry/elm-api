<?php

namespace App\Services;

use App\Enums\StatutCommandeVente;
use App\Enums\StatutCommissionVente;
use App\Enums\StatutFactureVente;
use App\Enums\StatutVersementCommission;
use App\Models\CommandeVente;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommandeVenteAnnulationService
{
    /**
     * Annule une commande de vente de façon idempotente et transactionnelle.
     *
     * Règles métier :
     *  - Idempotent : si déjà annulée, retourne la commande telle quelle.
     *  - Bloquant   : encaissements existants sur la facture → 422.
     *  - Bloquant   : commission déjà versée (totalement) → 422.
     *  - Cascade    : restaure le stock, annule facture + commission + versements en_attente.
     *
     * @throws \RuntimeException  Messages métier destinés à remonter en 422.
     */
    public function annuler(CommandeVente $commande, User $par, string $motif): CommandeVente
    {
        // Idempotence : déjà annulée → rien à faire
        if ($commande->isAnnulee()) {
            return $commande;
        }

        // Charger les relations nécessaires (sans scope site pour les contrôles)
        $commande->loadMissing(['facture.encaissements', 'commission.versements', 'lignes']);

        // ── Contrôles bloquants ────────────────────────────────────────────

        if ($commande->facture && $commande->facture->encaissements()->exists()) {
            throw new \RuntimeException(
                'Impossible d\'annuler : des encaissements ont été enregistrés sur cette commande.'
            );
        }

        if ($commande->commission) {
            $statutCommission = $commande->commission->statut;
            if ($statutCommission === StatutCommissionVente::VERSEE) {
                throw new \RuntimeException(
                    'Impossible d\'annuler : la commission a déjà été intégralement versée.'
                );
            }
        }

        // ── Transaction avec verrous ───────────────────────────────────────

        return DB::transaction(function () use ($commande, $par, $motif) {

            // 1. Restaurer le stock pour chaque ligne (lockForUpdate sur stocks)
            foreach ($commande->lignes as $ligne) {
                $stock = Stock::where('produit_id', $ligne->produit_id)
                    ->where('site_id', $commande->site_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->ajuster((int) $ligne->qte);
                }
            }

            // 2. Annuler la facture
            if ($commande->facture && ! $commande->facture->isAnnulee()) {
                $commande->facture->update([
                    'statut_facture' => StatutFactureVente::ANNULEE,
                ]);
            }

            // 3. Annuler commission + versements en attente
            if ($commande->commission) {
                $commande->commission->versements()
                    ->where('statut', StatutVersementCommission::EN_ATTENTE->value)
                    ->update(['statut' => StatutVersementCommission::ANNULE->value]);

                $commande->commission->update([
                    'statut' => StatutCommissionVente::ANNULEE->value,
                ]);
            }

            // 4. Marquer la commande comme annulée
            $commande->update([
                'statut'           => StatutCommandeVente::ANNULEE,
                'motif_annulation' => $motif,
                'annulee_at'       => now(),
                'annulee_par'      => $par->id,
            ]);

            return $commande->fresh([
                'createdBy:id,nom,prenom,phone',
                'annuleePar:id,nom,prenom',
                'vehicule',
                'lignes.produit',
                'facture',
                'commission',
            ]);
        });
    }
}
