<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use ApiResponse;

    public function login(Request $request): JsonResponse
    {
        try {
            // Validation
            $validated = $request->validate([
                'phone' => ['required', 'string'],
                'password' => ['required', 'string'],
                'remember_me' => ['nullable', 'boolean'],
            ]);

            $user = User::where('phone', $validated['phone'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'phone' => ['Les informations de connexion sont incorrectes.'],
                ]);
            }

            if (!$user->is_active) {
                return $this->forbiddenResponse('Votre compte est désactivé. Veuillez contacter l\'administrateur.');
            }

            $expiresAt = ($validated['remember_me'] ?? false)
                ? now()->addDays(30)
                : now()->addMinutes(config('sanctum.expiration', 120));

            $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;
            $user->updateLastLogin($request->ip());

            Log::info('Utilisateur connecté', ['user_id' => $user->id]);

            return $this->successResponse([
                'user' => $user->makeHidden(['roles', 'permissions']),
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $expiresAt->diffInSeconds(now()),
            ], 'Connexion réussie');

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Identifiants incorrects');

        } catch (\Exception $e) {
            Log::error('Erreur lors de la connexion', ['error' => $e->getMessage()]);
            return $this->errorResponse('Erreur lors de la connexion', $e->getMessage());
        }
    }
}