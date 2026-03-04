<?php

namespace Database\Seeders;

use App\Enums\OrganisationStatut;
use App\Models\Organisation;
use Illuminate\Database\Seeder;

/**
 * Crée l'organisation par défaut ELM Guinée.
 *
 * Idempotent : firstOrCreate sur le code unique.
 * Doit être appelé AVANT UsineSeeder pour que les usines puissent
 * récupérer l'organisation_id.
 */
class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        Organisation::firstOrCreate(
            ['code' => 'ELM-GN'],
            [
                'nom'         => 'ELM Guinée',
                'email'       => 'contact@elm.gn',
                'phone'       => null,
                'pays'        => 'Guinee',
                'ville'       => 'Conakry',
                'quartier'    => 'Matoto',
                'description' => 'Organisation principale — ELM Guinée',
                'statut'      => OrganisationStatut::ACTIVE,
            ]
        );

        $this->command->info('OrganisationSeeder : organisation ELM-GN créée ou existante.');
    }
}
