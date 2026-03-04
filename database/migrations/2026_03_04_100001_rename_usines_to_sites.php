<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename usine → site across the entire schema.
 *
 * Tables renamed:
 *   usines        → sites
 *   user_usines   → user_sites
 *   produit_usines → produit_sites
 *
 * Columns renamed (usine_id → site_id):
 *   produits, prestataires, clients, packings, versements, parametres,
 *   vehicules, sorties_vehicules, factures_ventes, commandes_ventes,
 *   commission_ventes, versements_commission, livreurs, proprietaires,
 *   stocks, user_sites, produit_sites
 *
 * Special:
 *   users.default_usine_id → users.default_site_id
 */
return new class extends Migration
{
    public function up(): void
    {
        $mysql = DB::getDriverName() === 'mysql';

        if ($mysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        // ── Rename tables ──────────────────────────────────────────────────
        Schema::rename('usines', 'sites');
        Schema::rename('user_usines', 'user_sites');
        Schema::rename('produit_usines', 'produit_sites');

        // ── Rename usine_id → site_id in pivot / config tables ────────────
        Schema::table('user_sites', function (Blueprint $table) {
            $table->renameColumn('usine_id', 'site_id');
        });

        Schema::table('produit_sites', function (Blueprint $table) {
            $table->renameColumn('usine_id', 'site_id');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->renameColumn('usine_id', 'site_id');
        });

        // ── Rename usine_id → site_id in business tables ──────────────────
        foreach ([
            'produits',
            'prestataires',
            'clients',
            'packings',
            'versements',
            'parametres',
            'vehicules',
            'sorties_vehicules',
            'factures_ventes',
            'commandes_ventes',
            'commission_ventes',
            'versements_commission',
            'livreurs',
            'proprietaires',
        ] as $tableName) {
            if (Schema::hasColumn($tableName, 'usine_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->renameColumn('usine_id', 'site_id');
                });
            }
        }

        // ── Rename denormalized column on users ────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('default_usine_id', 'default_site_id');
        });

        if ($mysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        $mysql = DB::getDriverName() === 'mysql';

        if ($mysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        Schema::rename('sites', 'usines');
        Schema::rename('user_sites', 'user_usines');
        Schema::rename('produit_sites', 'produit_usines');

        Schema::table('user_usines', function (Blueprint $table) {
            $table->renameColumn('site_id', 'usine_id');
        });

        Schema::table('produit_usines', function (Blueprint $table) {
            $table->renameColumn('site_id', 'usine_id');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->renameColumn('site_id', 'usine_id');
        });

        foreach ([
            'produits',
            'prestataires',
            'clients',
            'packings',
            'versements',
            'parametres',
            'vehicules',
            'sorties_vehicules',
            'factures_ventes',
            'commandes_ventes',
            'commission_ventes',
            'versements_commission',
            'livreurs',
            'proprietaires',
        ] as $tableName) {
            if (Schema::hasColumn($tableName, 'site_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->renameColumn('site_id', 'usine_id');
                });
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('default_site_id', 'default_usine_id');
        });

        if ($mysql) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
};
