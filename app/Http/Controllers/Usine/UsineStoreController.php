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
 * POST /api/v1/usines — Siège uniquement
 */
class UsineStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request): JsonResponse
    {
        if (!$request->user()->isSiege()) {
            return $this->forbiddenResponse('Seul le siège peut créer des usines.');
        }

        $validated = $request->validate([
            'nom'          => ['required', 'string', 'max:255'],
            'code'         => ['required', 'string', 'max:50', 'unique:usines,code', 'regex:/^[A-Z0-9_-]+$/'],
            'type'         => ['required', Rule::enum(UsineType::class)],
            'statut'       => ['nullable', Rule::enum(UsineStatut::class)],
            'localisation' => ['nullable', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'parent_id'    => ['nullable', 'integer', 'exists:usines,id'],
        ]);

        $usine = Usine::create($validated);

        return $this->createdResponse($usine->fresh(), 'Usine créée avec succès');
    }
}
