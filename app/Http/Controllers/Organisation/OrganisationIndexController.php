<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganisationResource;
use App\Http\Traits\ApiResponse;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/organisations — Liste toutes les organisations (super_admin uniquement).
 */
class OrganisationIndexController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request): JsonResponse
    {
        $organisations = Organisation::with('forfait')
            ->withCount(['sites', 'users'])
            ->orderBy('nom')
            ->get();

        return $this->successResponse(
            OrganisationResource::collection($organisations),
            'Liste des organisations récupérée'
        );
    }
}
