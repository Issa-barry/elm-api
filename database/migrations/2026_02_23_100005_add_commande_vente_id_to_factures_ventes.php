<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures_ventes', function (Blueprint $table) {
            $table->unsignedBigInteger('commande_vente_id')->nullable()->after('vehicule_id');
            $table->foreign('commande_vente_id')->references('id')->on('commandes_ventes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('factures_ventes', function (Blueprint $table) {
            $table->dropForeign(['commande_vente_id']);
            $table->dropColumn('commande_vente_id');
        });
    }
};
