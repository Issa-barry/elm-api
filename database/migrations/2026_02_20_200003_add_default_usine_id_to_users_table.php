<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_usine_id')
                ->nullable()
                ->after('language')
                ->constrained('usines')
                ->nullOnDelete()
                ->comment('Usine affichée par défaut (raccourci vers user_usines.is_default)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_usine_id']);
            $table->dropColumn('default_usine_id');
        });
    }
};
