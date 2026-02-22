<?php

namespace App\Http\Controllers\Vehicules;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Vehicule;
use Illuminate\Support\Facades\Storage;

class VehiculeDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(int $id)
    {
        $vehicule = Vehicule::find($id);

        if (!$vehicule) {
            return $this->notFoundResponse('Véhicule non trouvé');
        }

        if (!empty($vehicule->photo_path)) {
            Storage::disk('public')->delete($vehicule->photo_path);
        }
        $vehicule->delete();

        return $this->successResponse(null, 'Véhicule supprimé');
    }
}
