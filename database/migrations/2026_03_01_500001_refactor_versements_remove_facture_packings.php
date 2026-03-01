<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supprime le module FacturePacking et relie les versements directement aux packings.
 *
 * Ordre des opérations :
 *  1. Ajouter versements.packing_id (nullable pour le backfill)
 *  2. Backfill : relier chaque versement à son packing via facture_packings
 *  3. Supprimer les versements orphelins (facture sans packing correspondant)
 *  4. Rendre versements.packing_id NOT NULL
 *  5. Supprimer FK + colonne versements.facture_packing_id
 *  6. Supprimer index composite packings_statut_facture_index
 *  7. Supprimer FK + colonne packings.facture_id
 *  8. Supprimer la table facture_packings
 *  9. Migrer les anciens statuts packings → nouveaux
 * 10. Recalculer packings.statut depuis les versements backfillés
 */
return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------------
        // 1. Ajouter versements.packing_id (nullable pour le backfill)
        // ----------------------------------------------------------------
        Schema::table('versements', function (Blueprint $table) {
            $table->foreignId('packing_id')
                ->nullable()
                ->after('id')
                ->constrained('packings')
                ->onDelete('cascade');
        });

        // ----------------------------------------------------------------
        // 2. Backfill : pour chaque versement, trouver le packing lié
        //    via packings.facture_id = versements.facture_packing_id.
        //    Si une facture couvre plusieurs packings, on prend celui
        //    avec le montant le plus élevé (LIMIT 1 ORDER BY montant DESC).
        // ----------------------------------------------------------------
        DB::statement("
            UPDATE versements v
            SET v.packing_id = (
                SELECT p.id
                FROM packings p
                WHERE p.facture_id = v.facture_packing_id
                  AND p.deleted_at IS NULL
                ORDER BY p.montant DESC, p.id ASC
                LIMIT 1
            )
            WHERE v.facture_packing_id IS NOT NULL
              AND v.deleted_at IS NULL
        ");

        // ----------------------------------------------------------------
        // 3. Supprimer les versements qui n'ont pas pu être reliés
        //    (facture sans packing, ou versements déjà soft-deleted)
        // ----------------------------------------------------------------
        DB::table('versements')
            ->whereNull('packing_id')
            ->whereNull('deleted_at')
            ->delete();

        // ----------------------------------------------------------------
        // 4. Rendre versements.packing_id NOT NULL
        // ----------------------------------------------------------------
        Schema::table('versements', function (Blueprint $table) {
            $table->foreignId('packing_id')->nullable(false)->change();
        });

        // ----------------------------------------------------------------
        // 5. Supprimer la FK et la colonne versements.facture_packing_id
        // ----------------------------------------------------------------
        Schema::table('versements', function (Blueprint $table) {
            $table->dropForeign(['facture_packing_id']);

            // L'index peut s'appeler versements_facture_packing_id_index
            if (Schema::hasIndex('versements', 'versements_facture_packing_id_index')) {
                $table->dropIndex('versements_facture_packing_id_index');
            }

            $table->dropColumn('facture_packing_id');
        });

        // Ajouter l'index sur packing_id
        Schema::table('versements', function (Blueprint $table) {
            $table->index('packing_id');
        });

        // ----------------------------------------------------------------
        // 6. Supprimer l'index composite packings_statut_facture_index
        // ----------------------------------------------------------------
        Schema::table('packings', function (Blueprint $table) {
            if (Schema::hasIndex('packings', 'packings_statut_facture_index')) {
                $table->dropIndex('packings_statut_facture_index');
            }
        });

        // ----------------------------------------------------------------
        // 7. Supprimer la FK et la colonne packings.facture_id
        // ----------------------------------------------------------------
        Schema::table('packings', function (Blueprint $table) {
            // Supprimer la FK (peut s'appeler packings_facture_id_foreign)
            $table->dropForeign(['facture_id']);

            if (Schema::hasIndex('packings', 'packings_facture_id_index')) {
                $table->dropIndex('packings_facture_id_index');
            }

            $table->dropColumn('facture_id');
        });

        // ----------------------------------------------------------------
        // 8. Supprimer la table facture_packings (plus aucune FK vers elle)
        // ----------------------------------------------------------------
        Schema::dropIfExists('facture_packings');

        // ----------------------------------------------------------------
        // 9. Migrer les anciens statuts packings → nouveaux
        //    a_valider → impayee  |  valide → impayee  |  annule → annulee
        // ----------------------------------------------------------------
        DB::statement("
            UPDATE packings
            SET statut = CASE
                WHEN statut IN ('a_valider', 'valide') THEN 'impayee'
                WHEN statut = 'annule'                 THEN 'annulee'
                ELSE statut
            END
            WHERE statut IN ('a_valider', 'valide', 'annule')
        ");

        // Corriger le default du champ statut
        DB::statement("ALTER TABLE packings ALTER COLUMN statut SET DEFAULT 'impayee'");

        // ----------------------------------------------------------------
        // 10. Recalculer packings.statut depuis les versements backfillés
        //     (uniquement pour les packings non annulés)
        // ----------------------------------------------------------------
        DB::statement("
            UPDATE packings pk
            SET pk.statut = (
                SELECT CASE
                    WHEN COALESCE(SUM(v.montant), 0) <= 0          THEN 'impayee'
                    WHEN COALESCE(SUM(v.montant), 0) >= pk.montant THEN 'payee'
                    ELSE 'partielle'
                END
                FROM versements v
                WHERE v.packing_id = pk.id
                  AND v.deleted_at IS NULL
            )
            WHERE pk.statut != 'annulee'
              AND pk.deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        throw new \RuntimeException(
            'Cette migration est destructive et ne peut pas être annulée automatiquement. ' .
            'Restaurez depuis une sauvegarde.'
        );
    }
};
