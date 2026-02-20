<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->unsignedInteger('seuil_alerte_stock')
                ->nullable()
                ->after('qte_stock')
                ->comment('Seuil alerte stock personnalisé (null = fallback paramètre global)');
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn('seuil_alerte_stock');
        });
    }
};
