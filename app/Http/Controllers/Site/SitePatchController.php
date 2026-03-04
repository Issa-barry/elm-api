<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\PatchSiteRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

/**
 * PATCH /api/v1/sites/{id} — Siège uniquement
 */
class SitePatchController extends Controller
{
    use ApiResponse;

    public function __invoke(PatchSiteRequest $request, int $id): JsonResponse
    {
        $site = Site::findOrFail($id);

        $site->update($request->validated());

        return $this->successResponse($site->fresh(), 'Site mis à jour avec succès');
    }
}
