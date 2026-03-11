<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiements partiels sur un versement de commission.
 *
 * Un VersementCommission est une créance attendue (montant_attendu).
 * On peut la régler en une ou plusieurs fois via des PaiementVersementCommission.
 * Même logique que EncaissementVente / FactureVente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements_versements_commission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites');
            $table->foreignId('versement_commission_id')
                  ->constrained('versements_commission')
                  ->cascadeOnDelete();

            $table->decimal('montant', 12, 2);
            $table->date('date_paiement');
            $table->string('mode_paiement', 50)->default('especes');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('verse_par')->nullable();
            $table->foreign('verse_par')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements_versements_commission');
    }
};
