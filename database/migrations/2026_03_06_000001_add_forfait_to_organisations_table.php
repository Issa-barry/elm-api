<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table des forfaits (plans d'abonnement)
        Schema::create('forfaits', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();          // starter | standard | premium
            $table->string('nom');                     // libellé affiché
            $table->decimal('prix', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Clé étrangère sur organisations
        Schema::table('organisations', function (Blueprint $table) {
            $table->foreignId('forfait_id')->nullable()->after('statut')
                  ->constrained('forfaits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Forfait::class);
            $table->dropColumn('forfait_id');
        });

        Schema::dropIfExists('forfaits');
    }
};
