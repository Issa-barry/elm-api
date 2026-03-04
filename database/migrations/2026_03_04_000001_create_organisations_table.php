<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étape 1/4 — Créer la table organisations (entité racine tenant).
 *
 * Idempotent : vérifie l'existence avant de créer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organisations')) {
            return;
        }

        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code', 50)->unique();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('pays', 100)->nullable()->default('Guinee');
            $table->string('ville', 100)->nullable();
            $table->string('quartier', 100)->nullable();
            $table->string('adresse', 500)->nullable();
            $table->text('description')->nullable();
            $table->enum('statut', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
