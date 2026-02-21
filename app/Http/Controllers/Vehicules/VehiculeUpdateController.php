<?php

namespace App\Http\Controllers\Vehicules;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicule\UpdateVehiculeRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Vehicule;
use Illuminate\Support\Facades\Storage;

class VehiculeUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdateVehiculeRequest $request, int $id)
    {
        $vehicule = Vehicule::find($id);

        if (!$vehicule) {
            return $this->notFoundResponse('Véhicule non trouvé');
        }

        $data = $request->validated();

        if ($request->hasFile('photo')) {
            Storage::disk('public')->delete($vehicule->photo_path);
            $data['photo_path'] = $request->file('photo')->store('vehicules', 'public');
        }

        unset($data['photo']);

        $vehicule->update($data);
        $vehicule->load(['proprietaire', 'livreurPrincipal']);

        return $this->successResponse($vehicule->fresh(), 'Véhicule mis à jour');
    }
}
