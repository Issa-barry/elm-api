<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── STEP 1 : Créer la table produit_usines ───────────────────────────
        Schema::create('produit_usines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('produit_id')
                ->constrained('produits')
                ->cascadeOnDelete();

            $table->foreignId('usine_id')
                ->constrained('usines')
                ->cascadeOnDelete();

            $table->boolean('is_active')->default(false)
                ->comment('Produit activé localement à ce point de vente');

            // Prix/coût locaux (null = utiliser le prix global du produit)
            $table->unsignedInteger('prix_usine')->nullable();
            $table->unsignedInteger('prix_achat')->nullable();
            $table->unsignedInteger('prix_vente')->nullable();
            $table->unsignedInteger('cout')->nullable();

            // TVA locale en points de pourcentage entiers (ex: 18 = 18 %)
            $table->unsignedSmallInteger('tva')->nullable()
                ->comment('Taux de TVA local (entier, ex: 18 = 18 %). null = pas de TVA');

            $table->timestamps();

            $table->unique(['produit_id', 'usine_id']);
            $table->index('usine_id');
            $table->index('produit_id');
        });

        // ── STEP 2 : Backfill ─────────────────────────────────────────────────
        // Pour chaque produit non-global attaché à une usine :
        //   - créer une ligne produit_usines avec is_active = true
        //   - recopier les prix globaux dans la config locale
        DB::statement("
            INSERT INTO produit_usines
                (produit_id, usine_id, is_active, prix_usine, prix_achat, prix_vente, cout, created_at, updated_at)
            SELECT
                p.id,
                p.usine_id,
                CASE WHEN p.statut = 'actif' THEN TRUE ELSE FALSE END,
                p.prix_usine,
                p.prix_achat,
                p.prix_vente,
                p.cout,
                NOW(),
                NOW()
            FROM produits p
            WHERE p.usine_id IS NOT NULL
              AND p.is_global = FALSE
              AND p.deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('produit_usines');
    }
};
