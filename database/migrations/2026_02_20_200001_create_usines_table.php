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
            $table->text('description')->nullable();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('usines')
                ->nullOnDelete()
                ->comment('Usine parente (le siège est la racine)');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usines');
    }
};
