<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sorties_vehicules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usine_id')->constrained('usines');
            $table->foreignId('vehicule_id')->constrained('vehicules');
            $table->unsignedBigInteger('livreur_id_effectif');
            $table->foreign('livreur_id_effectif')->references('id')->on('livreurs');
            $table->unsignedInteger('packs_charges');
            $table->unsignedInteger('packs_retour')->nullable();
            $table->timestamp('date_depart');
            $table->timestamp('date_retour')->nullable();
            $table->string('statut_sortie', 30)->default('en_cours'); // en_cours | retourne | cloture

            // Snapshots des règles de commission au moment du départ
            $table->string('snapshot_mode_commission', 30);
            $table->decimal('snapshot_valeur_commission', 12, 2);
            $table->decimal('snapshot_pourcentage_proprietaire', 5, 2);
            $table->decimal('snapshot_pourcentage_livreur', 5, 2);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sorties_vehicules');
    }
};
