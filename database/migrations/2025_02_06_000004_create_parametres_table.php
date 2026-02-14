<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->string('cle')->unique();
            $table->text('valeur')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json
            $table->string('groupe')->default('general'); // packing, paiement, general
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('groupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
    }
};
  