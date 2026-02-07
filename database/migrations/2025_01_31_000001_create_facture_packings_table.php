<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facture_packings', function (Blueprint $table) {
            $table->id();

            // Référence unique
            $table->string('reference')->unique();

            // Prestataire concerné
            $table->foreignId('prestataire_id')
                ->constrained('prestataires')
                ->onDelete('restrict');

            // Période couverte
            $table->date('periode_debut');
            $table->date('periode_fin');

            // Montants
            $table->integer('montant_total')->default(0);
            $table->integer('nb_packings')->default(0);

            // Statut (impayee, partielle, payee, annulee)
            $table->string('statut')->default('impayee');

            // Notes
            $table->text('notes')->nullable();

            // Traçabilité
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('prestataire_id');
            $table->index('statut');
            $table->index(['periode_debut', 'periode_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facture_packings');
    }
};
