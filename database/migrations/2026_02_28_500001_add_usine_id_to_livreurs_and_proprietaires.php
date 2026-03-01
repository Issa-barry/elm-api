<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rattacher livreurs et propriétaires à une usine.
 *
 * Avant cette migration, ces deux tables étaient globales (pool partagé).
 * Après, chaque enregistrement appartient à une usine.
 *
 * Backfill : les enregistrements existants sont rattachés à la première
 * usine active trouvée (ou laissés NULL si aucune usine n'existe encore).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── livreurs ──────────────────────────────────────────────────────
        Schema::table('livreurs', function (Blueprint $table) {
            $table->foreignId('usine_id')
                ->nullable()
                ->after('id')
                ->constrained('usines')
                ->nullOnDelete();

            $table->index('usine_id');
        });

        // ── proprietaires ─────────────────────────────────────────────────
        Schema::table('proprietaires', function (Blueprint $table) {
            $table->foreignId('usine_id')
                ->nullable()
                ->after('id')
                ->constrained('usines')
                ->nullOnDelete();

            $table->index('usine_id');
        });

        // ── Backfill : rattacher les enregistrements existants ────────────
        // On prend la première usine active (ordre id ASC) comme usine par défaut.
        // Si la table usines est vide, usine_id reste NULL.
        $defaultUsineId = DB::table('usines')
            ->where('statut', 'active')
            ->orderBy('id')
            ->value('id');

        if ($defaultUsineId) {
            DB::table('livreurs')
                ->whereNull('usine_id')
                ->update(['usine_id' => $defaultUsineId]);

            DB::table('proprietaires')
                ->whereNull('usine_id')
                ->update(['usine_id' => $defaultUsineId]);
        }
    }

    public function down(): void
    {
        Schema::table('livreurs', function (Blueprint $table) {
            $table->dropForeign(['usine_id']);
            $table->dropIndex(['usine_id']);
            $table->dropColumn('usine_id');
        });

        Schema::table('proprietaires', function (Blueprint $table) {
            $table->dropForeign(['usine_id']);
            $table->dropIndex(['usine_id']);
            $table->dropColumn('usine_id');
        });
    }
};
