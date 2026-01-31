<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TokenController extends Controller
{
    use ApiResponse;

    /**
     * Rafraîchir le token
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Révoquer le token actuel
            $request->user()->currentAccessToken()->delete();

            // Créer un nouveau token
            $token = $user->createToken(
                'auth_token',
                ['*'],
                now()->addMinutes(config('sanctum.expiration', 120))
            )->plainTextToken;

            Log::info('Token rafraîchi', [
                'user_id' => $user->id
            ]);

            return $this->successResponse([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration', 120) * 60,
            ], 'Token rafraîchi avec succès');

        } catch (\Exception $e) {
            Log::error('Erreur lors du rafraîchissement du token', [
                'user_id' => optional($request->user())->id,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                'Erreur lors du rafraîchissement du token',
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Vérifier la validité du token
     */
    public function check(Request $request): JsonResponse
    {
        return $this->successResponse([
            'valid' => true,
            'user' => $request->user()
        ], 'Token valide');
    }
}