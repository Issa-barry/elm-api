<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Workflow simplifié : facture liée directement au véhicule (sans sortie).
     * Ajout de vehicule_id, packs_charges et snapshots de commission.
     */
    public function up(): void
    {
        Schema::table('factures_livraisons', function (Blueprint $table) {
            // Lien direct vers le véhicule (workflow simplifié)
            $table->unsignedBigInteger('vehicule_id')->nullable()->after('sortie_vehicule_id');

            // Nombre de packs chargés pour ce chargement
            $table->unsignedInteger('packs_charges')->nullable()->after('vehicule_id');

            // Snapshots des règles de commission au moment de la facture
            $table->string('snapshot_mode_commission', 20)->nullable()->after('packs_charges');
            $table->decimal('snapshot_valeur_commission', 12, 2)->nullable()->after('snapshot_mode_commission');
            $table->decimal('snapshot_pourcentage_proprietaire', 5, 2)->nullable()->after('snapshot_valeur_commission');
            $table->decimal('snapshot_pourcentage_livreur', 5, 2)->nullable()->after('snapshot_pourcentage_proprietaire');
        });
    }

    public function down(): void
    {
        Schema::table('factures_livraisons', function (Blueprint $table) {
            $table->dropColumn([
                'vehicule_id',
                'packs_charges',
                'snapshot_mode_commission',
                'snapshot_valeur_commission',
                'snapshot_pourcentage_proprietaire',
                'snapshot_pourcentage_livreur',
            ]);
        });
    }
};
