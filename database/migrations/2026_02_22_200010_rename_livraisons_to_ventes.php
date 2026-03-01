<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration de renommage sécurisée : factures_livraisons → factures_ventes.
 *
 * Garde : si la table factures_livraisons n'existe pas (fresh install ou déjà renommée),
 * la migration est skippée silencieusement.
 *
 * Couvre les bases MySQL dev qui ont déjà exécuté les anciennes migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('factures_livraisons')) {
            // Fresh install : les migrations originales créent déjà factures_ventes
            return;
        }

        Schema::disableForeignKeyConstraints();

        // 1. Rename table principale
        Schema::rename('factures_livraisons', 'factures_ventes');

        // 2. Supprimer sortie_vehicule_id (plus utilisé dans le flux vente)
        if (Schema::hasColumn('factures_ventes', 'sortie_vehicule_id')) {
            Schema::table('factures_ventes', function (Blueprint $table) {
                $table->dropColumn('sortie_vehicule_id');
            });
        }

        // 3. Backfill des statuts vers les nouvelles valeurs
        DB::table('factures_ventes')
            ->whereIn('statut_facture', ['emise', 'brouillon'])
            ->update(['statut_facture' => 'impayee']);
        DB::table('factures_ventes')
            ->where('statut_facture', 'partiellement_payee')
            ->update(['statut_facture' => 'partiel']);

        // 4. Renommer table encaissements_livraisons → encaissements_ventes + FK
        if (Schema::hasTable('encaissements_livraisons')) {
            if (Schema::hasColumn('encaissements_livraisons', 'facture_livraison_id')) {
                Schema::table('encaissements_livraisons', function (Blueprint $table) {
                    $table->renameColumn('facture_livraison_id', 'facture_vente_id');
                });
            }
            Schema::rename('encaissements_livraisons', 'encaissements_ventes');
        }

        // 5. Renommer FK dans deductions_commissions
        if (Schema::hasColumn('deductions_commissions', 'facture_livraison_id')) {
            Schema::table('deductions_commissions', function (Blueprint $table) {
                $table->renameColumn('facture_livraison_id', 'facture_vente_id');
            });
        }

        // 6. Renommer FK dans paiements_commissions
        if (Schema::hasColumn('paiements_commissions', 'facture_livraison_id')) {
            Schema::table('paiements_commissions', function (Blueprint $table) {
                $table->renameColumn('facture_livraison_id', 'facture_vente_id');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (!Schema::hasTable('factures_ventes')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        if (Schema::hasColumn('paiements_commissions', 'facture_vente_id')) {
            Schema::table('paiements_commissions', function (Blueprint $table) {
                $table->renameColumn('facture_vente_id', 'facture_livraison_id');
            });
        }

        if (Schema::hasColumn('deductions_commissions', 'facture_vente_id')) {
            Schema::table('deductions_commissions', function (Blueprint $table) {
                $table->renameColumn('facture_vente_id', 'facture_livraison_id');
            });
        }

        if (Schema::hasTable('encaissements_ventes')) {
            Schema::rename('encaissements_ventes', 'encaissements_livraisons');
        }
        if (Schema::hasColumn('encaissements_livraisons', 'facture_vente_id')) {
            Schema::table('encaissements_livraisons', function (Blueprint $table) {
                $table->renameColumn('facture_vente_id', 'facture_livraison_id');
            });
        }

        Schema::rename('factures_ventes', 'factures_livraisons');

        Schema::enableForeignKeyConstraints();
    }
};
