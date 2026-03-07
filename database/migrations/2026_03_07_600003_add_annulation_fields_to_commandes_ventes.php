<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes_ventes', function (Blueprint $table) {
            $table->string('statut', 20)->default('active')->after('updated_by');
            $table->text('motif_annulation')->nullable()->after('statut');
            $table->timestamp('annulee_at')->nullable()->after('motif_annulation');
            $table->foreignId('annulee_par')->nullable()->after('annulee_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commandes_ventes', function (Blueprint $table) {
            $table->dropForeign(['annulee_par']);
            $table->dropColumn(['statut', 'motif_annulation', 'annulee_at', 'annulee_par']);
        });
    }
};
