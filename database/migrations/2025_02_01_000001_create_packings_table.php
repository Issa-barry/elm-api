<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packings', function (Blueprint $table) {
            $table->id();

            // Relation avec le prestataire (machiniste)
            $table->foreignId('prestataire_id')
                ->constrained('prestataires')
                ->onDelete('restrict');

            // Date unique du packing
            $table->date('date');

            // Détails du packing
            $table->integer('nb_rouleaux')->default(0);
            $table->integer('prix_par_rouleau')->default(0);
            $table->integer('montant')->default(0);

            // Référence auto-générée
            $table->string('reference')->unique();

            // Statut et notes (a_valider, valide, annule)
            $table->string('statut')->default('valide');
            $table->text('notes')->nullable();

            // Traçabilité
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Relation avec facture_packings
            $table->foreignId('facture_id')
                ->nullable()
                ->constrained('facture_packings')
                ->nullOnDelete();

            // Index
            $table->index('statut');
            $table->index('facture_id');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packings');
    }
};
