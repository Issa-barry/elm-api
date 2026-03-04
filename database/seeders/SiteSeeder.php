<?php

namespace Database\Seeders;

use App\Enums\SiteStatut;
use App\Enums\SiteType;
use App\Models\Organisation;
use App\Models\Site;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        // Récupérer l'organisation par défaut (créée par OrganisationSeeder)
        $org = Organisation::where('code', 'ELM-GN')->first();

        $siege = Site::withTrashed()->firstOrNew([
            'code' => 'ELM-SIEGE',
        ]);

        $siege->fill([
            'nom'             => 'Usine de Matoto',
            'type'            => SiteType::SIEGE->value,
            'statut'          => SiteStatut::ACTIVE->value,
            'localisation'    => 'Conakry - Matoto',
            'pays'            => 'Guinee',
            'ville'           => 'Conakry',
            'quartier'        => 'Matoto',
            'description'     => 'Site siege - vue consolidee',
            'parent_id'       => null,
            'organisation_id' => $org?->id,
        ]);
        $siege->save();

        if ($siege->trashed()) {
            $siege->restore();
        }

        $site = Site::withTrashed()->firstOrNew([
            'code' => 'ELM-USN-01',
        ]);

        $site->fill([
            'nom'             => 'Usine de kaka',
            'type'            => SiteType::USINE->value,
            'statut'          => SiteStatut::ACTIVE->value,
            'localisation'    => 'Conakry - Kaka',
            'pays'            => 'Guinee',
            'ville'           => 'Conakry',
            'quartier'        => 'Kaka',
            'description'     => 'Site operationnel de kaka',
            'parent_id'       => $siege->id,
            'organisation_id' => $org?->id,
        ]);
        $site->save();

        if ($site->trashed()) {
            $site->restore();
        }
    }
}
