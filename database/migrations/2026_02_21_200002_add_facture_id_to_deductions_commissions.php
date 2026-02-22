<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Workflow simplifié : déductions liées à la facture (au lieu de la sortie).
     */
    public function up(): void
    {
        Schema::table('deductions_commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('facture_livraison_id')->nullable()->after('sortie_vehicule_id');
        });
    }

    public function down(): void
    {
        Schema::table('deductions_commissions', function (Blueprint $table) {
            $table->dropColumn('facture_livraison_id');
        });
    }
};
