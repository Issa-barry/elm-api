<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suppression de la logique de commission des véhicules.
     * Les factures sont désormais liées à des commandes, pas à des véhicules avec commission.
     */
    public function up(): void
    {
        Schema::table('vehicules', function (Blueprint $table) {
            $table->dropColumn([
                'mode_commission',
                'valeur_commission',
                'pourcentage_proprietaire',
                'pourcentage_livreur',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('vehicules', function (Blueprint $table) {
            $table->string('mode_commission', 30)->nullable()->after('pris_en_charge_par_usine');
            $table->decimal('valeur_commission', 12, 2)->nullable()->after('mode_commission');
            $table->decimal('pourcentage_proprietaire', 5, 2)->default(0)->after('valeur_commission');
            $table->decimal('pourcentage_livreur', 5, 2)->default(0)->after('pourcentage_proprietaire');
        });
    }
};
