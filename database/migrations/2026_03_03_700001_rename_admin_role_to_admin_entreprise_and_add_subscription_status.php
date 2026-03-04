<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Renommer le rôle admin → admin_entreprise (idempotent)
        if (
            DB::table('roles')->where('name', 'admin')->exists() &&
            ! DB::table('roles')->where('name', 'admin_entreprise')->exists()
        ) {
            DB::table('roles')->where('name', 'admin')->update(['name' => 'admin_entreprise']);
        }

        // 2. Ajouter subscription_status sur la table usines
        Schema::table('usines', function (Blueprint $table) {
            if (! Schema::hasColumn('usines', 'subscription_status')) {
                $table->string('subscription_status', 20)
                    ->default('active')
                    ->after('statut')
                    ->comment('active | trial | suspended | cancelled');
            }
        });
    }

    public function down(): void
    {
        // Renommer admin_entreprise → admin
        if (
            DB::table('roles')->where('name', 'admin_entreprise')->exists() &&
            ! DB::table('roles')->where('name', 'admin')->exists()
        ) {
            DB::table('roles')->where('name', 'admin_entreprise')->update(['name' => 'admin']);
        }

        Schema::table('usines', function (Blueprint $table) {
            if (Schema::hasColumn('usines', 'subscription_status')) {
                $table->dropColumn('subscription_status');
            }
        });
    }
};
