<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Étape 4/4 — Rendre organisation_id NOT NULL sur usines et users.
 *
 * MySQL uniquement : SQLite ne supporte pas ALTER COLUMN pour changer la nullabilité
 * sans recréer la table. En test (SQLite), la colonne reste nullable mais
 * le backfill (migration 000003) garantit que toutes les données existantes
 * sont rattachées. Les nouvelles entrées (tests) passent via les seeders
 * ou factories qui définissent explicitement organisation_id quand nécessaire.
 *
 * Prérequis : migration 000003 doit avoir tourné (aucun NULL restant en prod).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Vérification de sécurité : aucune ligne NULL ne doit subsister
        $nullUsines = DB::table('usines')->whereNull('organisation_id')->count();
        $nullUsers  = DB::table('users')->whereNull('organisation_id')->count();

        if ($nullUsines > 0 || $nullUsers > 0) {
            throw new \RuntimeException(
                "Migration 000004 annulée : des lignes sans organisation_id subsistent " .
                "(usines: {$nullUsines}, users: {$nullUsers}). " .
                "Vérifiez que la migration 000003 a bien tourné."
            );
        }

        // MySQL interdit MODIFY NOT NULL sur une colonne référencée par une FK ON DELETE SET NULL.
        // On doit donc : 1) supprimer la FK, 2) passer NOT NULL, 3) recréer la FK en RESTRICT.
        foreach (['usines', 'users'] as $table) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_organisation_id_foreign`");
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN organisation_id BIGINT UNSIGNED NOT NULL");
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_organisation_id_foreign`
                FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE RESTRICT");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (['usines', 'users'] as $table) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$table}_organisation_id_foreign`");
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN organisation_id BIGINT UNSIGNED NULL");
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_organisation_id_foreign`
                FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE SET NULL");
        }
    }
};
