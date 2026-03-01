<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sortie_vehicule_id')->nullable()->unique()->constrained('sorties_vehicules');
            $table->decimal('commission_brute_totale', 14, 2);
            $table->decimal('part_proprietaire_brute', 14, 2);
            $table->decimal('part_livreur_brute', 14, 2);
            $table->decimal('part_proprietaire_nette', 14, 2);
            $table->decimal('part_livreur_nette', 14, 2);
            $table->date('date_paiement')->nullable();
            $table->string('statut', 30)->default('en_attente'); // en_attente | paye
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements_commissions');
    }
};
