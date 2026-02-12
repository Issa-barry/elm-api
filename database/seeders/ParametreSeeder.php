<?php

namespace Database\Seeders;

use App\Models\Parametre;
use Illuminate\Database\Seeder;

class ParametreSeeder extends Seeder
{
    public function run(): void
    {
        $parametres = [
            // Packing
            [
                'cle' => Parametre::CLE_PRIX_ROULEAU_DEFAUT,
                'valeur' => '500',
                'type' => Parametre::TYPE_INTEGER,
                'groupe' => Parametre::GROUPE_PACKING,
                'description' => 'Prix par rouleau par défaut pour le packing',
            ],
            [
                'cle' => Parametre::CLE_PRODUIT_ROULEAU_ID,
                'valeur' => null,
                'type' => Parametre::TYPE_INTEGER,
                'groupe' => Parametre::GROUPE_PACKING,
                'description' => 'ID du produit rouleau utilisé pour le packing (gestion du stock)',
            ],
        ];

        foreach ($parametres as $parametre) {
            Parametre::updateOrCreate(
                ['cle' => $parametre['cle']],
                $parametre
            );
        }
    }
}
