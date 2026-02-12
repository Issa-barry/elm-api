<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versements', function (Blueprint $table) {
            $table->id();

            // Référence unique
            $table->string('reference')->unique();

            // Lien avec la facture
            $table->foreignId('facture_packing_id')
                ->constrained('facture_packings')
                ->onDelete('cascade');

            // Montant versé
            $table->integer('montant');

            // Date et mode de paiement
            $table->date('date_versement');
            $table->string('mode_paiement')->default('especes');

            // Notes
            $table->text('notes')->nullable();

            // Traçabilité
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('facture_packing_id');
            $table->index('date_versement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versements');
    }
};
