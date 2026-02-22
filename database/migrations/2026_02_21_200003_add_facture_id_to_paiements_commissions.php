<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Workflow simplifié : paiement de commission lié à la facture (au lieu de la sortie).
     * Une facture ne peut avoir qu'un seul paiement de commission (unique).
     */
    public function up(): void
    {
        Schema::table('paiements_commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('facture_livraison_id')->nullable()->unique()->after('sortie_vehicule_id');
        });
    }

    public function down(): void
    {
        Schema::table('paiements_commissions', function (Blueprint $table) {
            $table->dropColumn('facture_livraison_id');
        });
    }
};
