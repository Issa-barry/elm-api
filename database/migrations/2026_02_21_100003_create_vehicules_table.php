<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usine_id')->constrained('usines');
            $table->string('nom_vehicule', 100);
            $table->string('immatriculation', 20);
            $table->string('type_vehicule', 50);
            $table->unsignedInteger('capacite_packs');
            $table->foreignId('proprietaire_id')->constrained('proprietaires');
            $table->unsignedBigInteger('livreur_principal_id')->nullable();
            $table->foreign('livreur_principal_id')->references('id')->on('livreurs');
            $table->boolean('pris_en_charge_par_usine')->default(false);
            $table->string('mode_commission', 30); // forfait | pourcentage
            $table->decimal('valeur_commission', 12, 2);
            $table->decimal('pourcentage_proprietaire', 5, 2)->default(0);
            $table->decimal('pourcentage_livreur', 5, 2)->default(0);
            $table->string('photo_path');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Immatriculation unique par usine
            $table->unique(['usine_id', 'immatriculation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicules');
    }
};
