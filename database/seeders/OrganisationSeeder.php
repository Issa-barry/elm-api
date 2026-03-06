<?php

namespace Database\Seeders;

use App\Enums\OrganisationStatut;
use App\Models\Forfait;
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
        $starter = Forfait::where('slug', 'starter')->first();

        $org = Organisation::firstOrCreate(
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
                'forfait_id'  => $starter?->id,
            ]
        );

        // Assigner le forfait si l'org existait déjà sans forfait
        if ($org->forfait_id === null && $starter) {
            $org->update(['forfait_id' => $starter->id]);
        }

        $this->command->info('OrganisationSeeder : organisation ELM-GN créée ou existante.');
    }
}
