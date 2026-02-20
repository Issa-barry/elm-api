<?php

namespace App\Http\Controllers\Usine;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PATCH /api/v1/usines/{usineId}/set-default
 *
 * Permet à l'utilisateur connecté de changer son usine par défaut,
 * parmi les usines auxquelles il est affecté.
 */
class UsineChangeDefaultController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $usineId): JsonResponse
    {
        $user = $request->user();

        // Vérifier que l'usine est accessible par l'utilisateur
        if (!$user->isSiege() && !$user->hasUsineAccess($usineId)) {
            return $this->forbiddenResponse('Vous n\'êtes pas affecté à cette usine.');
        }

        // Réinitialiser l'ancien is_default
        $user->userUsines()->update(['is_default' => false]);

        // Mettre à jour le pivot pour l'usine choisie
        $user->usines()->updateExistingPivot($usineId, ['is_default' => true]);

        // Mettre à jour la colonne dénormalisée
        $user->update(['default_usine_id' => $usineId]);

        return $this->successResponse(
            ['default_usine_id' => $usineId],
            'Usine par défaut mise à jour'
        );
    }
}
