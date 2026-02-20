<?php

namespace App\Http\Controllers\Usine;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Usine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/usines
 *
 * - Siège : toutes les usines actives
 * - Non-siège : uniquement les usines auxquelles l'utilisateur est affecté
 */
class UsineIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isSiege()) {
            $usines = Usine::with(['parent:id,nom,code'])
                ->orderBy('type')
                ->orderBy('nom')
                ->get();
        } else {
            $usines = $user->usines()
                ->with(['parent:id,nom,code'])
                ->orderBy('type')
                ->orderBy('nom')
                ->get()
                ->map(function ($usine) {
                    $usine->mon_role = $usine->pivot->role;
                    return $usine;
                });
        }

        return $this->successResponse($usines, 'Liste des usines récupérée');
    }
}
