<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StoreSiteRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/sites — Siège uniquement
 */
class SiteStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreSiteRequest $request): JsonResponse
    {
        $site = Site::create($request->validated());

        return $this->createdResponse($site->fresh(), 'Site créé avec succès');
    }
}
