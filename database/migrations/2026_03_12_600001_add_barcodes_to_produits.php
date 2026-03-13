<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute code_interne + code_fournisseur (Code128) à la table produits.
 *
 * Stratégie de transition :
 *   - code_interne  : backfillé depuis `code` (valeur 12 chiffres existante)
 *   - code_fournisseur : NULL par défaut
 *   - `code` reste inchangé (rétrocompatibilité des clients existants)
 *
 * SQLite (tests) : code_interne reste NULLABLE (pas d'ALTER MODIFY).
 * MySQL  (prod)  : code_interne passe NOT NULL après backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            // Nullable pour le backfill ; rendu NOT NULL ensuite sur MySQL
            $table->string('code_interne', 50)->nullable()->after('code');
            $table->string('code_fournisseur', 100)->nullable()->after('code_interne');

            $table->unique('code_interne', 'produits_code_interne_unique');
            $table->index('code_fournisseur', 'produits_code_fournisseur_index');
        });

        // Backfill : code_interne ← code (déjà unique, 12 chiffres)
        // Pour les soft-deleted, on préfixe pour éviter toute collision future
        DB::table('produits')
            ->whereNull('deleted_at')
            ->whereNull('code_interne')
            ->whereNotNull('code')
            ->update(['code_interne' => DB::raw('`code`')]);

        if (DB::getDriverName() === 'mysql') {
            DB::table('produits')
                ->whereNotNull('deleted_at')
                ->whereNull('code_interne')
                ->whereNotNull('code')
                ->update(['code_interne' => DB::raw("CONCAT('DEL-', `code`, '-', `id`)")]);
        } else {
            // SQLite : utilise || pour la concaténation
            DB::table('produits')
                ->whereNotNull('deleted_at')
                ->whereNull('code_interne')
                ->whereNotNull('code')
                ->update(['code_interne' => DB::raw("'DEL-' || \"code\" || '-' || \"id\"")]);
        }

        // Produits sans code (edge case) : générer un identifiant minimal
        DB::table('produits')
            ->whereNull('code_interne')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('produits')
                    ->where('id', $row->id)
                    ->update(['code_interne' => 'PRD' . str_pad($row->id, 9, '0', STR_PAD_LEFT)]);
            });

        // MySQL uniquement : rendre NOT NULL
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE produits MODIFY code_interne VARCHAR(50) NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropUnique('produits_code_interne_unique');
            $table->dropIndex('produits_code_fournisseur_index');
            $table->dropColumn(['code_interne', 'code_fournisseur']);
        });
    }
};
