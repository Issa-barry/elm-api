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

            // Date de la facture
            $table->date('date');

            // Montants
            $table->integer('montant_total')->default(0);
            $table->integer('nb_packings')->default(0);

            // Paiement
            $table->date('date_paiement')->nullable();
            $table->string('mode_paiement')->default('especes');

            // Statut (impayee, partielle, payee, annulee)
            $table->string('statut')->default('impayee');

            // Notes
            $table->text('notes')->nullable();

            // Traçabilité
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('prestataire_id');
            $table->index('statut');
            $table->index('date');
            $table->index(['prestataire_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facture_packings');
    }
};
