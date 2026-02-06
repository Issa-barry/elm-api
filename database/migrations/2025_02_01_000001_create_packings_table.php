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

            // Période
            $table->date('date_debut');
            $table->date('date_fin');

            // Détails du packing
            $table->integer('nb_rouleaux')->default(0);
            $table->integer('prix_par_rouleau')->default(0);
            $table->integer('montant')->default(0);

            // Référence auto-générée
            $table->string('reference')->unique();

            // Statut et notes
            $table->string('statut')->default('en_cours');
            $table->text('notes')->nullable();

            // Traçabilité
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('statut');
            $table->index(['date_debut', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packings');
    }
};
