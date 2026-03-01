<?php

namespace App\Http\Controllers\Usine;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Usine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/usines/{id}
 *
 * - Siège : peut voir toutes les usines
 * - Non-siège : seulement les usines auxquelles il est affecté
 */
class UsineShowController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $user  = $request->user();
        $usine = Usine::with(['parent:id,nom,code', 'enfants:id,nom,code,type,statut'])->findOrFail($id);

        if (!$user->isSiege() && !$user->hasUsineAccess($id)) {
            return $this->forbiddenResponse('Vous n\'êtes pas affecté à cette usine.');
        }

        return $this->successResponse($usine, 'Détail de l\'usine récupéré');
    }
}
