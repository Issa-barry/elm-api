<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures_ventes', function (Blueprint $table) {
            if (!Schema::hasIndex('factures_ventes', 'factures_ventes_commande_vente_idx')) {
                $table->index('commande_vente_id', 'factures_ventes_commande_vente_idx');
            }
            if (!Schema::hasIndex('factures_ventes', 'factures_ventes_site_created_statut_deleted_idx')) {
                $table->index(
                    ['site_id', 'created_at', 'statut_facture', 'deleted_at'],
                    'factures_ventes_site_created_statut_deleted_idx'
                );
            }
            if (!Schema::hasIndex('factures_ventes', 'factures_ventes_created_statut_deleted_idx')) {
                $table->index(
                    ['created_at', 'statut_facture', 'deleted_at'],
                    'factures_ventes_created_statut_deleted_idx'
                );
            }
        });

        Schema::table('commandes_ventes', function (Blueprint $table) {
            if (!Schema::hasIndex('commandes_ventes', 'commandes_ventes_site_created_deleted_idx')) {
                $table->index(
                    ['site_id', 'created_at', 'deleted_at'],
                    'commandes_ventes_site_created_deleted_idx'
                );
            }
            if (!Schema::hasIndex('commandes_ventes', 'commandes_ventes_created_deleted_idx')) {
                $table->index(
                    ['created_at', 'deleted_at'],
                    'commandes_ventes_created_deleted_idx'
                );
            }
        });

        Schema::table('encaissements_ventes', function (Blueprint $table) {
            if (!Schema::hasIndex('encaissements_ventes', 'encaissements_ventes_facture_created_idx')) {
                $table->index(
                    ['facture_vente_id', 'created_at'],
                    'encaissements_ventes_facture_created_idx'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('encaissements_ventes', function (Blueprint $table) {
            if (Schema::hasIndex('encaissements_ventes', 'encaissements_ventes_facture_created_idx')) {
                $table->dropIndex('encaissements_ventes_facture_created_idx');
            }
        });

        Schema::table('commandes_ventes', function (Blueprint $table) {
            if (Schema::hasIndex('commandes_ventes', 'commandes_ventes_created_deleted_idx')) {
                $table->dropIndex('commandes_ventes_created_deleted_idx');
            }
            if (Schema::hasIndex('commandes_ventes', 'commandes_ventes_site_created_deleted_idx')) {
                $table->dropIndex('commandes_ventes_site_created_deleted_idx');
            }
        });

        Schema::table('factures_ventes', function (Blueprint $table) {
            if (Schema::hasIndex('factures_ventes', 'factures_ventes_created_statut_deleted_idx')) {
                $table->dropIndex('factures_ventes_created_statut_deleted_idx');
            }
            if (Schema::hasIndex('factures_ventes', 'factures_ventes_site_created_statut_deleted_idx')) {
                $table->dropIndex('factures_ventes_site_created_statut_deleted_idx');
            }
            if (Schema::hasIndex('factures_ventes', 'factures_ventes_commande_vente_idx')) {
                $table->dropIndex('factures_ventes_commande_vente_idx');
            }
        });
    }
};
