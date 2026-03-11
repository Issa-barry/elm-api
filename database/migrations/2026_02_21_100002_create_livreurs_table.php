<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livreurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usine_id')->nullable()->constrained('usines')->nullOnDelete();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('phone', 20)->unique();
            $table->string('email', 255)->nullable()->unique();
            $table->string('pays', 100)->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('quartier', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livreurs');
    }
};
