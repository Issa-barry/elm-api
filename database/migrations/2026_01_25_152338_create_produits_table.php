<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code')->unique();

            // Prix en GNF (entiers, pas de décimales)
            $table->unsignedBigInteger('prix_usine')->nullable();
            $table->unsignedBigInteger('prix_vente')->nullable();
            $table->unsignedBigInteger('prix_achat')->nullable();

            // Stock et coût
            $table->unsignedInteger('qte_stock')->default(0);
            $table->unsignedBigInteger('cout')->default(0);

            // Type : materiel, service, fabricable, achat_vente
            $table->string('type')->default('materiel');

            // Statut : brouillon, actif, inactif, archive, rupture_stock
            $table->string('statut')->default('brouillon');

            // Archivage
            $table->timestamp('archived_at')->nullable();

            // Infos complémentaires
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();

            // Tracking utilisateur
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Index pour performance
            $table->index(['statut', 'type']);
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
