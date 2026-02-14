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
     * Rafraichir le token
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $defaultTokenExpiration = (int) config('sanctum.default_expiration', 120);
            $rememberMeTokenExpirationDays = (int) config('sanctum.remember_me_expiration_days', 30);
            $currentToken = $request->user()->currentAccessToken();

            $currentTokenLifetimeMinutes = null;
            if ($currentToken?->created_at && $currentToken?->expires_at) {
                $currentTokenLifetimeMinutes = $currentToken->created_at->diffInMinutes($currentToken->expires_at);
            }

            $rememberMeThreshold = $defaultTokenExpiration;
            $isRememberMeToken = $currentTokenLifetimeMinutes !== null
                && $currentTokenLifetimeMinutes > $rememberMeThreshold;

            if ($currentToken) {
                $currentToken->delete();
            }

            $expiresAt = $isRememberMeToken
                ? now()->addDays($rememberMeTokenExpirationDays)
                : now()->addMinutes($defaultTokenExpiration);

            $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

            Log::info('Token rafraichi', [
                'user_id' => $user->id,
            ]);

            return $this->successResponse([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => now()->diffInSeconds($expiresAt),
                'expires_at' => $expiresAt->toISOString(),
                'remember_me' => $isRememberMeToken,
            ], 'Token rafraichi avec succes');

        } catch (\Exception $e) {
            Log::error('Erreur lors du rafraichissement du token', [
                'user_id' => optional($request->user())->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors du rafraichissement du token',
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Verifier la validite du token
     */
    public function check(Request $request): JsonResponse
    {
        return $this->successResponse([
            'valid' => true,
            'user' => $request->user(),
        ], 'Token valide');
    }
}