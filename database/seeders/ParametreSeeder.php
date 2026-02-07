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

            // Périodes de paiement
            [
                'cle' => Parametre::CLE_PERIODE_1_DEBUT,
                'valeur' => '1',
                'type' => Parametre::TYPE_INTEGER,
                'groupe' => Parametre::GROUPE_PAIEMENT,
                'description' => 'Jour de début de la période 1 (1ère quinzaine)',
            ],
            [
                'cle' => Parametre::CLE_PERIODE_1_FIN,
                'valeur' => '15',
                'type' => Parametre::TYPE_INTEGER,
                'groupe' => Parametre::GROUPE_PAIEMENT,
                'description' => 'Jour de fin de la période 1 (1ère quinzaine)',
            ],
            [
                'cle' => Parametre::CLE_PERIODE_2_DEBUT,
                'valeur' => '16',
                'type' => Parametre::TYPE_INTEGER,
                'groupe' => Parametre::GROUPE_PAIEMENT,
                'description' => 'Jour de début de la période 2 (2ème quinzaine)',
            ],
            [
                'cle' => Parametre::CLE_PERIODE_2_FIN,
                'valeur' => '0',
                'type' => Parametre::TYPE_INTEGER,
                'groupe' => Parametre::GROUPE_PAIEMENT,
                'description' => 'Jour de fin de la période 2 (0 = dernier jour du mois)',
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
