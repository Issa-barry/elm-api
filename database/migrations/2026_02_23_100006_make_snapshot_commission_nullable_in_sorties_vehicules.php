<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sorties_vehicules', function (Blueprint $table) {
            $table->string('snapshot_mode_commission', 30)->nullable()->change();
            $table->decimal('snapshot_valeur_commission', 12, 2)->nullable()->change();
            $table->decimal('snapshot_pourcentage_proprietaire', 5, 2)->nullable()->change();
            $table->decimal('snapshot_pourcentage_livreur', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sorties_vehicules', function (Blueprint $table) {
            $table->string('snapshot_mode_commission', 30)->nullable(false)->change();
            $table->decimal('snapshot_valeur_commission', 12, 2)->nullable(false)->change();
            $table->decimal('snapshot_pourcentage_proprietaire', 5, 2)->nullable(false)->change();
            $table->decimal('snapshot_pourcentage_livreur', 5, 2)->nullable(false)->change();
        });
    }
};
