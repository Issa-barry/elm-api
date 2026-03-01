<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('livreurs', function (Blueprint $table) {
            $table->string('email', 255)->nullable()->unique()->after('phone');
            $table->string('pays', 100)->nullable()->after('email');
            $table->string('ville', 100)->nullable()->after('pays');
            $table->string('quartier', 100)->nullable()->after('ville');
        });
    }

    public function down(): void
    {
        Schema::table('livreurs', function (Blueprint $table) {
            $table->dropColumn(['email', 'pays', 'ville', 'quartier']);
        });
    }
};
