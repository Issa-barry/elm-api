<?php

namespace App\Http\Controllers\Livreurs;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Livreur;
use Illuminate\Http\Request;

class LivreurIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        $livreurs = Livreur::query()
            ->when($request->boolean('inactifs'), fn ($q) => $q->where('is_active', false), fn ($q) => $q->where('is_active', true))
            ->orderBy('nom')
            ->paginate(20);

        return $this->successResponse($livreurs, 'Liste des livreurs');
    }
}
