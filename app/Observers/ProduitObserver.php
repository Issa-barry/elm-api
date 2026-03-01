<?php

namespace App\Observers;

use App\Models\Produit;

class ProduitObserver
{
    public function updated(Produit $produit): void
    {
        // La logique de notification stock a été déplacée vers StockObserver.
        // Cet observer peut accueillir d'autres événements produit à l'avenir.
    }
}
