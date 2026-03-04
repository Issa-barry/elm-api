<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étape 2/4 — Ajouter organisation_id (nullable) sur usines et users.
 *
 * Nullable pour permettre le déploiement sans downtime :
 * la migration 000003 backfille les données existantes,
 * la migration 000004 rend la colonne NOT NULL (MySQL uniquement).
 *
 * Idempotent : vérifie l'existence des colonnes avant d'agir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usines', function (Blueprint $table) {
            if (!Schema::hasColumn('usines', 'organisation_id')) {
                $table->unsignedBigInteger('organisation_id')->nullable()->after('id');
                $table->foreign('organisation_id')
                    ->references('id')
                    ->on('organisations')
                    ->nullOnDelete();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'organisation_id')) {
                $table->unsignedBigInteger('organisation_id')->nullable()->after('id');
                $table->foreign('organisation_id')
                    ->references('id')
                    ->on('organisations')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('usines', function (Blueprint $table) {
            if (Schema::hasColumn('usines', 'organisation_id')) {
                $table->dropForeign(['organisation_id']);
                $table->dropColumn('organisation_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'organisation_id')) {
                $table->dropForeign(['organisation_id']);
                $table->dropColumn('organisation_id');
            }
        });
    }
};
