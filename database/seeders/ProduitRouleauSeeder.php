<?php

namespace Database\Seeders;

use App\Enums\ProduitStatut;
use App\Enums\ProduitType;
use App\Models\Parametre;
use App\Models\Produit;
use Illuminate\Database\Seeder;

class ProduitRouleauSeeder extends Seeder
{
    public function run(): void
    {
        $produit = Produit::updateOrCreate(
            ['code' => 'ROULEAU-PACK'],
            [
                'nom' => 'Rouleau de packing',
                'type' => ProduitType::MATERIEL,
                'statut' => ProduitStatut::ACTIF,
                'prix_achat' => 500,
                'qte_stock' => 0,
            ]
        );

        // Lier le produit au paramètre global
        Parametre::set(Parametre::CLE_PRODUIT_ROULEAU_ID, $produit->id);
    }
}
