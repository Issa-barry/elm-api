<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── STEP 1 : Ajouter is_global à produits ────────────────────────────
        Schema::table('produits', function (Blueprint $table) {
            $table->boolean('is_global')
                ->default(false)
                ->after('usine_id')
                ->comment('Produit global : visible par toutes les usines, stock géré par usine');
        });

        // ── STEP 2 : Convertir rupture_stock → actif avant de supprimer le case ──
        DB::statement("UPDATE produits SET statut = 'actif' WHERE statut = 'rupture_stock'");

        // ── STEP 3 : Rendre produits.usine_id nullable (produits système = NULL) ──
        Schema::table('produits', function (Blueprint $table) {
            $table->dropForeign(['usine_id']);
            $table->unsignedBigInteger('usine_id')->nullable()->change();
            $table->foreign('usine_id')
                ->references('id')
                ->on('usines')
                ->restrictOnDelete();
        });

        // ── STEP 4 : Créer la table stocks ───────────────────────────────────
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')
                ->constrained('produits')
                ->restrictOnDelete();
            $table->foreignId('usine_id')
                ->constrained('usines')
                ->restrictOnDelete();
            $table->unsignedInteger('qte_stock')->default(0);
            $table->unsignedInteger('seuil_alerte_stock')->nullable()
                ->comment('Seuil alerte personnalisé par usine (null = paramètre global)');
            $table->timestamps();
            $table->unique(['produit_id', 'usine_id']);
            $table->index('usine_id');
            $table->index('produit_id');
        });

        // ── STEP 5 : Data migration — copier les stocks existants ─────────────
        // Les services n'ont pas de stock (qte_stock toujours 0, pas utile)
        DB::statement("
            INSERT INTO stocks (produit_id, usine_id, qte_stock, seuil_alerte_stock, created_at, updated_at)
            SELECT id, usine_id, qte_stock, seuil_alerte_stock, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM produits
            WHERE usine_id IS NOT NULL
              AND type != 'service'
        ");

        // ── STEP 6 : Supprimer les anciennes colonnes de produits ─────────────
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn(['qte_stock', 'seuil_alerte_stock']);
        });
    }

    public function down(): void
    {
        // Remettre les colonnes sur produits
        Schema::table('produits', function (Blueprint $table) {
            $table->unsignedInteger('qte_stock')->default(0)->after('is_global');
            $table->unsignedInteger('seuil_alerte_stock')->nullable()->after('qte_stock');
        });

        // Copier les stocks vers produits (usine_id matching)
        DB::statement("
            UPDATE produits p
            JOIN stocks s ON s.produit_id = p.id AND s.usine_id = p.usine_id
            SET p.qte_stock = s.qte_stock,
                p.seuil_alerte_stock = s.seuil_alerte_stock
        ");

        Schema::dropIfExists('stocks');

        // Remettre usine_id NOT NULL et supprimer is_global
        Schema::table('produits', function (Blueprint $table) {
            $table->dropForeign(['usine_id']);
            $table->unsignedBigInteger('usine_id')->nullable(false)->change();
            $table->foreign('usine_id')
                ->references('id')
                ->on('usines')
                ->restrictOnDelete();
            $table->dropColumn('is_global');
        });
    }
};
