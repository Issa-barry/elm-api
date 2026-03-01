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
        $statut = $request->input('statut'); // 'actif' | 'inactif' | null = tous

        $vehicules = Vehicule::with(['proprietaire', 'livreurPrincipal'])
            ->when($statut === 'actif',   fn ($q) => $q->where('is_active', true))
            ->when($statut === 'inactif', fn ($q) => $q->where('is_active', false))
            ->orderBy('nom_vehicule')
            ->paginate(20);

        return $this->successResponse(
            $vehicules->through(fn ($v) => VehiculeResource::make($v)),
            'Liste des véhicules'
        );
    }
}
