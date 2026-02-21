<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\SortieVehicule;

class SortieVehiculeShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $sortie = SortieVehicule::with(['vehicule.proprietaire', 'livreurEffectif', 'factureLivraison.encaissements', 'deductions', 'paiementCommission'])->find($id);

        if (!$sortie) {
            return $this->notFoundResponse('Sortie véhicule non trouvée');
        }

        return $this->successResponse($sortie);
    }
}
