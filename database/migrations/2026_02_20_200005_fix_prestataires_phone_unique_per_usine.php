<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remplace la contrainte unique globale sur prestataires.phone
 * par une contrainte composite (usine_id, phone) pour permettre
 * le même numéro dans deux usines différentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestataires', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->unique(['usine_id', 'phone'], 'prestataires_usine_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('prestataires', function (Blueprint $table) {
            try {
                $table->dropUnique('prestataires_usine_phone_unique');
            } catch (\Throwable) {}

            $table->unique(['phone']);
        });
    }
};
