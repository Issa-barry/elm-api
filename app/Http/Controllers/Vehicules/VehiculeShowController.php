<?php

namespace App\Http\Controllers\Vehicules;

use App\Http\Controllers\Controller;
use App\Http\Resources\VehiculeResource;
use App\Http\Traits\ApiResponse;
use App\Models\Vehicule;

class VehiculeShowController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $vehicule = Vehicule::with(['proprietaire', 'livreurPrincipal'])->find($id);

        if (!$vehicule) {
            return $this->notFoundResponse('Véhicule non trouvé');
        }

        return $this->successResponse(VehiculeResource::make($vehicule));
    }
}
