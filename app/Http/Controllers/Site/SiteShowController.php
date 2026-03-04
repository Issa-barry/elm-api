<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/sites/{id}
 *
 * - Siège : peut voir tous les sites
 * - Non-siège : seulement les sites auxquels il est affecté
 */
class SiteShowController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $site = Site::with(['parent:id,nom,code', 'enfants:id,nom,code,type,statut'])->findOrFail($id);

        if (!$user->isSiege() && !$user->hasSiteAccess($id)) {
            return $this->forbiddenResponse('Vous n\'êtes pas affecté à ce site.');
        }

        return $this->successResponse($site, 'Détail du site récupéré');
    }
}
