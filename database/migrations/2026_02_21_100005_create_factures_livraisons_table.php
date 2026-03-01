<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factures_ventes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usine_id')->constrained('usines');
            $table->string('reference', 60)->unique();
            $table->decimal('montant_brut', 14, 2);
            $table->decimal('montant_net', 14, 2);
            $table->string('statut_facture', 30)->default('impayee'); // impayee|partiel|payee|annulee
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factures_ventes');
    }
};
