<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/sites
 *
 * - Siège : tous les sites actifs
 * - Non-siège : uniquement les sites auxquels l'utilisateur est affecté
 */
class SiteIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isSiege()) {
            $sites = Site::with(['parent:id,nom,code'])
                ->orderBy('type')
                ->orderBy('nom')
                ->get();
        } else {
            $sites = $user->sites()
                ->with(['parent:id,nom,code'])
                ->orderBy('type')
                ->orderBy('nom')
                ->get()
                ->map(function ($site) {
                    $site->mon_role = $site->pivot->role;
                    return $site;
                });
        }

        return $this->successResponse($sites, 'Liste des sites récupérée');
    }
}
