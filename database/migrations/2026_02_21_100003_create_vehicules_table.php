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
            $table->string('marque', 100)->nullable();
            $table->string('modele', 100)->nullable();
            $table->string('immatriculation', 20);
            $table->string('type_vehicule', 50);
            $table->unsignedInteger('capacite_packs');
            $table->foreignId('proprietaire_id')->constrained('proprietaires');
            $table->unsignedBigInteger('livreur_principal_id')->nullable();
            $table->foreign('livreur_principal_id')->references('id')->on('livreurs');
            $table->boolean('pris_en_charge_par_usine')->default(false);
            $table->decimal('taux_commission_livreur', 5, 2)->default(100.00);
            $table->boolean('commission_active')->default(true);
            $table->string('photo_path')->nullable();
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
