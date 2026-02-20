<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_usines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('usine_id')->constrained('usines')->cascadeOnDelete();
            $table->enum('role', ['owner_siege', 'admin_siege', 'manager', 'staff', 'viewer'])
                  ->default('staff')
                  ->comment('Rôle de l\'utilisateur dans cette usine');
            $table->boolean('is_default')->default(false)
                  ->comment('Usine affichée par défaut si aucun X-Usine-Id n\'est envoyé');
            $table->timestamps();

            $table->unique(['user_id', 'usine_id'], 'user_usine_unique');
            $table->index('usine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_usines');
    }
};
