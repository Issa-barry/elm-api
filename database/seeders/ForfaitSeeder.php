<?php

namespace Database\Seeders;

use App\Models\Forfait;
use Illuminate\Database\Seeder;

/**
 * Crée les 3 forfaits de base.
 * Idempotent : firstOrCreate sur le slug unique.
 * Doit être appelé AVANT OrganisationSeeder.
 */
class ForfaitSeeder extends Seeder
{
    public function run(): void
    {
        $forfaits = [
            ['slug' => 'starter',  'nom' => 'Starter',  'prix' => 0,     'description' => 'Forfait de démarrage'],
            ['slug' => 'standard', 'nom' => 'Standard', 'prix' => 50000, 'description' => 'Forfait standard'],
            ['slug' => 'premium',  'nom' => 'Premium',  'prix' => 100000,'description' => 'Forfait premium'],
        ];

        foreach ($forfaits as $data) {
            Forfait::firstOrCreate(['slug' => $data['slug']], $data);
        }

        $this->command->info('ForfaitSeeder : 3 forfaits créés ou existants.');
    }
}
