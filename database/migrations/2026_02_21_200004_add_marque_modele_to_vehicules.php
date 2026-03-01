<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajout des champs marque et modele au véhicule.
     * nom_vehicule est désormais composé automatiquement si non fourni (marque + modele).
     */
    public function up(): void
    {
        Schema::table('vehicules', function (Blueprint $table) {
            $table->string('marque', 100)->nullable()->after('nom_vehicule');
            $table->string('modele', 100)->nullable()->after('marque');
        });
    }

    public function down(): void
    {
        Schema::table('vehicules', function (Blueprint $table) {
            $table->dropColumn(['marque', 'modele']);
        });
    }
};
