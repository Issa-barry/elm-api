<?php

namespace App\Http\Controllers\Proprietaires;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProprietaireResource;
use App\Http\Traits\ApiResponse;
use App\Models\Proprietaire;
use Illuminate\Http\Request;

class ProprietaireIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        $statut = $request->input('statut'); // 'actif' | 'inactif' | null = tous

        $proprietaires = Proprietaire::query()
            ->when($statut === 'actif',   fn ($q) => $q->where('is_active', true))
            ->when($statut === 'inactif', fn ($q) => $q->where('is_active', false))
            ->orderBy('nom')
            ->paginate(20);

        return $this->successResponse(
            $proprietaires->through(fn ($p) => ProprietaireResource::make($p)),
            'Liste des propriétaires'
        );
    }
}