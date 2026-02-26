<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les champs de commission (nouveau système) sur les véhicules.
     * taux_commission_livreur : % de la commission allant au livreur (reste au propriétaire).
     * commission_active : false pour les véhicules pris en charge par l'usine.
     */
    public function up(): void
    {
        Schema::table('vehicules', function (Blueprint $table) {
            $table->decimal('taux_commission_livreur', 5, 2)->default(100.00)->after('pris_en_charge_par_usine');
            $table->boolean('commission_active')->default(true)->after('taux_commission_livreur');
        });
    }

    public function down(): void
    {
        Schema::table('vehicules', function (Blueprint $table) {
            $table->dropColumn(['taux_commission_livreur', 'commission_active']);
        });
    }
};
