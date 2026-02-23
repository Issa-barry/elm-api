<?php

namespace App\Http\Controllers\Ventes;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CommandeVente;

class CommandeVenteIndexController extends Controller
{
    use ApiResponse;

    public function __invoke()
    {
        $commandes = CommandeVente::with(['vehicule.livreurPrincipal', 'vehicule.proprietaire', 'lignes.produit', 'facture'])
            ->when(request('vehicule_id'), fn ($q, $v) => $q->where('vehicule_id', $v))
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->successResponse($commandes, 'Liste des commandes de vente');
    }
}
