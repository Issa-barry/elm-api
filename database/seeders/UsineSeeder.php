<?php

namespace Database\Seeders;

use App\Enums\UsineStatut;
use App\Enums\UsineType;
use App\Models\Usine;
use Illuminate\Database\Seeder;

class UsineSeeder extends Seeder
{
    public function run(): void
    {
        $siege = Usine::withTrashed()->firstOrNew([
            'code' => 'ELM-SIEGE',
        ]);

        $siege->fill([
            'nom' => 'Usine de Matoto',
            'type' => UsineType::SIEGE->value,
            'statut' => UsineStatut::ACTIVE->value,
            'localisation' => 'Conakry - Matoto',
            'pays' => 'Guinee',
            'ville' => 'Conakry',
            'quartier' => 'Matoto',
            'description' => 'Usine siege - vue consolidee',
            'parent_id' => null,
        ]);
        $siege->save();

        if ($siege->trashed()) {
            $siege->restore();
        }

        $usine = Usine::withTrashed()->firstOrNew([
            'code' => 'ELM-USN-01',
        ]);

        $usine->fill([
            'nom' => 'Usine de kaka',
            'type' => UsineType::USINE->value,
            'statut' => UsineStatut::ACTIVE->value,
            'localisation' => 'Conakry - Kaka',
            'pays' => 'Guinee',
            'ville' => 'Conakry',
            'quartier' => 'Kaka',
            'description' => 'Usine operationnelle de kaka',
            'parent_id' => $siege->id,
        ]);
        $usine->save();

        if ($usine->trashed()) {
            $usine->restore();
        }
    }
}
