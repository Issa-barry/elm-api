<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encaissements_livraisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_livraison_id')->constrained('factures_livraisons');
            $table->decimal('montant', 14, 2);
            $table->date('date_encaissement');
            $table->string('mode_paiement', 30); // especes|mobile_money|virement|cheque
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encaissements_livraisons');
    }
};
