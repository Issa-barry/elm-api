<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogoutController extends Controller
{
    use ApiResponse;

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            Log::info('Utilisateur déconnecté', [
                'user_id' => $request->user()->id
            ]);

            return $this->successResponse(null, 'Déconnexion réussie');

        } catch (\Exception $e) {
            Log::error('Erreur lors de la déconnexion', ['error' => $e->getMessage()]);
            return $this->errorResponse('Erreur lors de la déconnexion', $e->getMessage());
        }
    }

    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $request->user()->tokens()->delete();

            Log::info('Utilisateur déconnecté de tous les appareils', [
                'user_id' => $request->user()->id
            ]);

            return $this->successResponse(null, 'Déconnexion de tous les appareils réussie');

        } catch (\Exception $e) {
            Log::error('Erreur lors de la déconnexion de tous les appareils', ['error' => $e->getMessage()]);
            return $this->errorResponse('Erreur lors de la déconnexion', $e->getMessage());
        }
    }
}