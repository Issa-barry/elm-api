<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deductions_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sortie_vehicule_id')->constrained('sorties_vehicules');
            $table->string('cible', 30);        // proprietaire | livreur | usine
            $table->string('type_deduction', 30); // carburant | reparation | avance | autre
            $table->decimal('montant', 12, 2);
            $table->text('commentaire')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deductions_commissions');
    }
};
