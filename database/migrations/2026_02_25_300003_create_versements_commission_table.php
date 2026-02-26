<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versements_commission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usine_id')->constrained('usines');
            $table->foreignId('commission_vente_id')->constrained('commission_ventes')->cascadeOnDelete();

            $table->string('beneficiaire_type', 30); // 'livreur' | 'proprietaire'
            $table->unsignedBigInteger('beneficiaire_id');

            $table->decimal('montant_attendu', 12, 2);
            $table->decimal('montant_verse', 12, 2)->nullable();

            $table->string('statut', 30)->default('en_attente');
            $table->unsignedBigInteger('verse_par')->nullable();
            $table->timestamp('verse_at')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            // Contrainte d'unicité : un seul versement par bénéficiaire par commission
            $table->unique(['commission_vente_id', 'beneficiaire_type'], 'vc_commission_beneficiaire_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versements_commission');
    }
};
