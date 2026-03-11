<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill : transition des commissions "en_attente" dont la facture est déjà "payee".
 *
 * Contexte : avant ce refactoring, les commissions étaient créées avec statut "en_attente"
 * à la création de la commande, puis passées à "eligible" lors de l'encaissement.
 * Désormais, elles sont créées directement en "eligible" lors du paiement.
 *
 * Ce backfill aligne les données existantes sur la nouvelle règle métier.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite ne supporte pas le UPDATE multi-table → requête équivalente via sous-requête
            DB::statement("
                UPDATE commission_ventes
                SET statut = 'eligible', eligible_at = datetime('now')
                WHERE statut = 'en_attente'
                  AND deleted_at IS NULL
                  AND commande_vente_id IN (
                      SELECT cv.id
                      FROM commandes_ventes cv
                      JOIN factures_ventes fv ON fv.commande_vente_id = cv.id
                      WHERE fv.statut_facture = 'payee'
                        AND fv.deleted_at IS NULL
                        AND cv.deleted_at IS NULL
                  )
            ");

            return;
        }

        // MySQL / MariaDB
        DB::statement("
            UPDATE commission_ventes cv
            JOIN commandes_ventes cmd ON cmd.id = cv.commande_vente_id
            JOIN factures_ventes fv   ON fv.commande_vente_id = cmd.id
            SET cv.statut      = 'eligible',
                cv.eligible_at = NOW()
            WHERE cv.statut        = 'en_attente'
              AND cv.deleted_at    IS NULL
              AND fv.statut_facture = 'payee'
              AND fv.deleted_at    IS NULL
              AND cmd.deleted_at   IS NULL
        ");
    }

    public function down(): void
    {
        // Non réversible : on ne sait pas quelles lignes étaient en_attente avant
    }
};
