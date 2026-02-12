<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestataires', function (Blueprint $table) {
            $table->string('type')->default('fournisseur')->after('specialite');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('prestataires', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
