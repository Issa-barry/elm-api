<?php

namespace App\Http\Controllers\Vehicules;

use App\Http\Controllers\Controller;
use App\Http\Resources\VehiculeResource;
use App\Http\Traits\ApiResponse;
use App\Models\Vehicule;
use Illuminate\Http\Request;

class VehiculeIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        $vehicules = Vehicule::with(['proprietaire', 'livreurPrincipal'])
            ->when(!$request->boolean('inactifs'), fn ($q) => $q->where('is_active', true))
            ->orderBy('nom_vehicule')
            ->paginate(20);

        return $this->successResponse(
            $vehicules->through(fn ($v) => VehiculeResource::make($v)),
            'Liste des véhicules'
        );
    }
}
