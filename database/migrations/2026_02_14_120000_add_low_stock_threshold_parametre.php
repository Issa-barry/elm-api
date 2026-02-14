<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('parametres')
            ->where('cle', 'seuil_stock_faible')
            ->exists();

        if ($exists) {
            DB::table('parametres')
                ->where('cle', 'seuil_stock_faible')
                ->update([
                    'type' => 'integer',
                    'groupe' => 'general',
                    'description' => 'Seuil a partir duquel le stock est considere comme faible (0 = desactive)',
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('parametres')->insert([
            'cle' => 'seuil_stock_faible',
            'valeur' => '10',
            'type' => 'integer',
            'groupe' => 'general',
            'description' => 'Seuil a partir duquel le stock est considere comme faible (0 = desactive)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('parametres')
            ->where('cle', 'seuil_stock_faible')
            ->delete();
    }
};
