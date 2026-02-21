<?php

namespace App\Http\Controllers\Vehicules;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicule\StoreVehiculeRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Vehicule;
use Illuminate\Support\Facades\Storage;

class VehiculeStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreVehiculeRequest $request)
    {
        $data = $request->validated();

        $data['photo_path'] = $request->file('photo')->store('vehicules', 'public');

        $vehicule = Vehicule::create($data);
        $vehicule->load(['proprietaire', 'livreurPrincipal']);

        return $this->createdResponse($vehicule, 'Véhicule créé avec succès');
    }
}
