<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commandes_ventes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usine_id')->constrained('usines');
            $table->unsignedBigInteger('vehicule_id');
            $table->foreign('vehicule_id')->references('id')->on('vehicules');
            $table->string('reference', 60)->unique();
            $table->decimal('total_commande', 14, 2)->default(0);

            // Tracking utilisateur
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            // Annulation
            $table->string('statut', 20)->default('active');
            $table->text('motif_annulation')->nullable();
            $table->timestamp('annulee_at')->nullable();
            $table->foreignId('annulee_par')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes_ventes');
    }
};
