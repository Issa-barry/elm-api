<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DELETE /api/v1/sites/{id} — Siège uniquement (soft delete)
 */
class SiteDestroyController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isSiege()) {
            return $this->forbiddenResponse('Seul le siège peut supprimer des sites.');
        }

        $site = Site::findOrFail($id);

        if ($site->isSiege()) {
            return $this->forbiddenResponse('Le site siège ne peut pas être supprimé.');
        }

        $site->delete();

        return $this->successResponse(null, 'Site supprimé avec succès');
    }
}
