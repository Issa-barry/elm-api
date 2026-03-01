<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_ventes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usine_id')->constrained('usines')->cascadeOnDelete();
            $table->foreignId('commande_vente_id')->unique()->constrained('commandes_ventes')->cascadeOnDelete();
            $table->foreignId('vehicule_id')->constrained('vehicules');
            $table->foreignId('livreur_id')->nullable()->constrained('livreurs')->nullOnDelete();
            $table->foreignId('proprietaire_id')->nullable()->constrained('proprietaires')->nullOnDelete();

            // Snapshots figés à la création de la commande
            $table->decimal('taux_livreur_snapshot', 5, 2);
            $table->decimal('montant_commission_total', 12, 2);
            $table->decimal('part_livreur', 12, 2);
            $table->decimal('part_proprietaire', 12, 2);

            $table->string('statut', 30)->default('en_attente');
            $table->timestamp('eligible_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_ventes');
    }
};
