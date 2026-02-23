<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suppression des snapshots de commission des factures de vente.
     * Les factures sont désormais auto-créées depuis les commandes (qui ont leur propre logique de prix).
     */
    public function up(): void
    {
        Schema::table('factures_ventes', function (Blueprint $table) {
            $table->dropColumn([
                'packs_charges',
                'snapshot_mode_commission',
                'snapshot_valeur_commission',
                'snapshot_pourcentage_proprietaire',
                'snapshot_pourcentage_livreur',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('factures_ventes', function (Blueprint $table) {
            $table->unsignedInteger('packs_charges')->nullable()->after('vehicule_id');
            $table->string('snapshot_mode_commission', 20)->nullable()->after('packs_charges');
            $table->decimal('snapshot_valeur_commission', 12, 2)->nullable()->after('snapshot_mode_commission');
            $table->decimal('snapshot_pourcentage_proprietaire', 5, 2)->nullable()->after('snapshot_valeur_commission');
            $table->decimal('snapshot_pourcentage_livreur', 5, 2)->nullable()->after('snapshot_pourcentage_proprietaire');
        });
    }
};
