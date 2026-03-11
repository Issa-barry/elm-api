<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill : aligne les statuts de commission et de commande sur les nouveaux libellés.
 *
 * StatutCommissionVente :
 *   en_attente           → impayee
 *   eligible             → impayee
 *   partiellement_versee → partielle
 *   versee               → payee
 *
 * StatutCommandeVente :
 *   Nouvelle valeur "cloturee" : facture payée + commission payée (ou absente).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Renommer les statuts de commission ─────────────────────────────

        DB::table('commission_ventes')
            ->whereIn('statut', ['en_attente', 'eligible'])
            ->update(['statut' => 'impayee']);

        DB::table('commission_ventes')
            ->where('statut', 'partiellement_versee')
            ->update(['statut' => 'partielle']);

        DB::table('commission_ventes')
            ->where('statut', 'versee')
            ->update(['statut' => 'payee']);

        // ── 2. Backfill commissions "en_attente" du backfill précédent ────────
        //   (migration 600004 peut avoir écrit 'eligible' ; déjà couvert ci-dessus)

        // ── 3. Clôturer les commandes dont facture + commission sont payées ───

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                UPDATE commandes_ventes
                SET statut = 'cloturee'
                WHERE statut = 'active'
                  AND deleted_at IS NULL
                  AND id IN (
                      SELECT cv.id
                      FROM commandes_ventes cv
                      JOIN factures_ventes fv ON fv.commande_vente_id = cv.id
                      WHERE fv.statut_facture = 'payee'
                        AND fv.deleted_at IS NULL
                        AND (
                            -- pas de commission
                            NOT EXISTS (
                                SELECT 1 FROM commission_ventes c
                                WHERE c.commande_vente_id = cv.id AND c.deleted_at IS NULL
                            )
                            OR
                            -- commission payée
                            EXISTS (
                                SELECT 1 FROM commission_ventes c
                                WHERE c.commande_vente_id = cv.id
                                  AND c.statut = 'payee'
                                  AND c.deleted_at IS NULL
                            )
                        )
                  )
            ");
            return;
        }

        // MySQL / MariaDB
        DB::statement("
            UPDATE commandes_ventes cv
            JOIN factures_ventes fv ON fv.commande_vente_id = cv.id
            SET cv.statut = 'cloturee'
            WHERE cv.statut        = 'active'
              AND cv.deleted_at    IS NULL
              AND fv.statut_facture = 'payee'
              AND fv.deleted_at    IS NULL
              AND (
                  NOT EXISTS (
                      SELECT 1 FROM commission_ventes c
                      WHERE c.commande_vente_id = cv.id AND c.deleted_at IS NULL
                  )
                  OR EXISTS (
                      SELECT 1 FROM commission_ventes c
                      WHERE c.commande_vente_id = cv.id
                        AND c.statut = 'payee'
                        AND c.deleted_at IS NULL
                  )
              )
        ");
    }

    public function down(): void
    {
        // Réversion des statuts commission
        DB::table('commission_ventes')
            ->where('statut', 'impayee')
            ->update(['statut' => 'eligible']);

        DB::table('commission_ventes')
            ->where('statut', 'partielle')
            ->update(['statut' => 'partiellement_versee']);

        DB::table('commission_ventes')
            ->where('statut', 'payee')
            ->update(['statut' => 'versee']);

        // Réversion commandes clôturées → active
        DB::table('commandes_ventes')
            ->where('statut', 'cloturee')
            ->update(['statut' => 'active']);
    }
};
