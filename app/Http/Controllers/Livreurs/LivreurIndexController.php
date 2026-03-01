<?php

namespace App\Http\Controllers\Livreurs;

use App\Http\Controllers\Controller;
use App\Http\Resources\LivreurResource;
use App\Http\Traits\ApiResponse;
use App\Models\Livreur;
use Illuminate\Http\Request;

class LivreurIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        $statut = $request->input('statut'); // 'actif' | 'inactif' | null = tous

        $livreurs = Livreur::query()
            ->when($statut === 'actif',   fn ($q) => $q->where('is_active', true))
            ->when($statut === 'inactif', fn ($q) => $q->where('is_active', false))
            ->orderBy('nom')
            ->paginate(20);

        return $this->successResponse(
            $livreurs->through(fn ($l) => LivreurResource::make($l)),
            'Liste des livreurs'
        );
    }
}