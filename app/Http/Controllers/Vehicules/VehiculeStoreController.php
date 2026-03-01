<?php

namespace App\Http\Controllers\Vehicules;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicule\StoreVehiculeRequest;
use App\Http\Resources\VehiculeResource;
use App\Http\Traits\ApiResponse;
use App\Models\Vehicule;

class VehiculeStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreVehiculeRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('vehicules', 'public');
        }

        unset($data['photo']);

        $vehicule = Vehicule::create($data);
        $vehicule->load(['proprietaire', 'livreurPrincipal']);

        return $this->createdResponse(VehiculeResource::make($vehicule), 'Véhicule créé avec succès');
    }
}
