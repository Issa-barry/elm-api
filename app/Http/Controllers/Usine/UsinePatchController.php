<?php

namespace App\Http\Controllers\Usine;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usine\PatchUsineRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Usine;
use Illuminate\Http\JsonResponse;

/**
 * PATCH /api/v1/usines/{id} — Siège uniquement
 */
class UsinePatchController extends Controller
{
    use ApiResponse;

    public function __invoke(PatchUsineRequest $request, int $id): JsonResponse
    {
        $usine = Usine::findOrFail($id);

        $usine->update($request->validated());

        return $this->successResponse($usine->fresh(), 'Usine mise à jour avec succès');
    }
}
