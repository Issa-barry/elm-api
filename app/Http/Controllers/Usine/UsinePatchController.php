<?php

namespace App\Http\Controllers\Usine;

use App\Enums\UsineStatut;
use App\Enums\UsineType;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Usine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * PATCH /api/v1/usines/{id} — Siège uniquement
 */
class UsinePatchController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, int $id): JsonResponse
    {
        if (!$request->user()->isSiege()) {
            return $this->forbiddenResponse('Seul le siège peut modifier des usines.');
        }

        $usine = Usine::findOrFail($id);

        $validated = $request->validate([
            'nom'          => ['sometimes', 'string', 'max:255'],
            'code'         => ['sometimes', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('usines', 'code')->ignore($usine->id)],
            'type'         => ['sometimes', Rule::enum(UsineType::class)],
            'statut'       => ['sometimes', Rule::enum(UsineStatut::class)],
            'localisation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description'  => ['sometimes', 'nullable', 'string', 'max:2000'],
            'parent_id'    => ['sometimes', 'nullable', 'integer', Rule::exists('usines', 'id')->whereNot('id', $id)],
        ]);

        $usine->update($validated);

        return $this->successResponse($usine->fresh(), 'Usine mise à jour avec succès');
    }
}
