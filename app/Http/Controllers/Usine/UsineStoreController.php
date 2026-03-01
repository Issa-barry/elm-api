<?php

namespace App\Http\Controllers\Usine;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usine\StoreUsineRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Usine;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/usines — Siège uniquement
 */
class UsineStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreUsineRequest $request): JsonResponse
    {
        $usine = Usine::create($request->validated());

        return $this->createdResponse($usine->fresh(), 'Usine créée avec succès');
    }
}
