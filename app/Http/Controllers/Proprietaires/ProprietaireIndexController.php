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
        $proprietaires = Proprietaire::query()
            ->when(
                $request->boolean('inactifs'),
                fn ($q) => $q->where('is_active', false),
                fn ($q) => $q->where('is_active', true)
            )
            ->orderBy('nom')
            ->paginate(20);

        return $this->successResponse(
            $proprietaires->through(fn ($p) => ProprietaireResource::make($p)),
            'Liste des propriétaires'
        );
    }
}