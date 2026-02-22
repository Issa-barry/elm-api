<?php

namespace App\Http\Controllers\Livraisons;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\SortieVehicule;
use Illuminate\Http\Request;

class SortieVehiculeIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        $sorties = SortieVehicule::with(['vehicule', 'livreurEffectif', 'factureLivraison'])
            ->when($request->input('statut'), fn ($q, $s) => $q->where('statut_sortie', $s))
            ->orderByDesc('date_depart')
            ->paginate(20);

        return $this->successResponse($sorties, 'Liste des sorties véhicules');
    }
}
