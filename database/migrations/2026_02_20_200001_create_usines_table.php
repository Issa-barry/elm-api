<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usines', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('code')->unique()->comment('Code court unique, ex: ELM-SIEGE, ELM-USN-01');
            $table->enum('type', ['siege', 'usine'])->default('usine');
            $table->enum('statut', ['active', 'inactive'])->default('active');
            $table->string('localisation')->nullable()->comment('Ville / adresse');
            $table->string('pays', 100)->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('quartier', 100)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('usines')
                ->nullOnDelete()
                ->comment('Usine parente (le siège est la racine)');
            $table->timestamps();
            $table->softDeletes();
        });

        // Ajout du FK users.default_usine_id maintenant que usines existe
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('default_usine_id')
                ->references('id')->on('usines')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_usine_id']);
        });
        Schema::dropIfExists('usines');
    }
};
