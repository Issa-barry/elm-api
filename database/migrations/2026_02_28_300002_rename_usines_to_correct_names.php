<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renomme les usines fondatrices créées par la migration de backfill (200004)
 * pour qu'elles portent leurs vrais noms opérationnels.
 *
 *  ELM Siège        → Usine de Matoto  (siège,               code ELM-SIEGE)
 *  Usine Principale → Usine de kaka    (usine opérationnelle, code ELM-USN-01)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('usines')
            ->where('code', 'ELM-SIEGE')
            ->update(['nom' => 'Usine de Matoto', 'updated_at' => now()]);

        DB::table('usines')
            ->where('code', 'ELM-USN-01')
            ->update(['nom' => 'Usine de kaka', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('usines')
            ->where('code', 'ELM-SIEGE')
            ->update(['nom' => 'ELM Siège', 'updated_at' => now()]);

        DB::table('usines')
            ->where('code', 'ELM-USN-01')
            ->update(['nom' => 'Usine Principale', 'updated_at' => now()]);
    }
};
