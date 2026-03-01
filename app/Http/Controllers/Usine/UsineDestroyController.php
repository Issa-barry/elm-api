<?php

namespace App\Http\Controllers\Usine;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Usine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DELETE /api/v1/usines/{id} — Siège uniquement (soft delete)
 */
class UsineDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isSiege()) {
            return $this->forbiddenResponse('Seul le siège peut supprimer des usines.');
        }

        $usine = Usine::findOrFail($id);

        if ($usine->isSiege()) {
            return $this->forbiddenResponse('L\'usine siège ne peut pas être supprimée.');
        }

        $usine->delete();

        return $this->successResponse(null, 'Usine supprimée avec succès');
    }
}
