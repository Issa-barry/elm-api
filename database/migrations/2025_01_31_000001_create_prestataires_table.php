<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestataires', function (Blueprint $table) {
            $table->id();

            // Identité
            $table->string('nom');
            $table->string('prenom');
            $table->string('raison_sociale')->nullable();

            // Contact
            $table->string('phone')->unique();
            $table->string('email')->nullable()->unique();

            // Localisation
            $table->string('pays')->default('Guinée');
            $table->string('code_pays', 5)->default('GN');
            $table->string('code_phone_pays', 5)->default('+224');
            $table->string('ville')->nullable();
            $table->string('quartier')->nullable();
            $table->string('adresse')->nullable();

            // Professionnel
            $table->string('specialite')->nullable();
            $table->integer('tarif_horaire')->nullable();
            $table->text('notes')->nullable();

            // Référence (auto-généré)
            $table->string('reference')->unique();

            // Statut
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestataires');
    }
};
