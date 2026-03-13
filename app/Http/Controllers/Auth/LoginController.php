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
                'phone'          => ['required', 'string'],
                'code_phone_pays' => ['nullable', 'string', 'regex:/^\+[0-9]{1,4}$/'],
                'password'       => ['required', 'string'],
                'remember_me'    => ['nullable', 'boolean'],
            ]);

            $phone = $this->normalizePhone(
                $validated['phone'],
                $validated['code_phone_pays'] ?? null
            );

            $user = User::where('phone', $phone)->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'phone' => ['Les informations de connexion sont incorrectes.'],
                ]);
            }

            if ($user->is_archived) {
                return $this->forbiddenResponse('Votre compte a été archivé. Veuillez contacter l\'administrateur.');
            }

            if (!$user->is_active) {
                return $this->forbiddenResponse('Votre compte est désactivé. Veuillez contacter l\'administrateur.');
            }

            $isRememberMe = (bool) ($validated['remember_me'] ?? false);
            $rememberMeTokenExpirationDays = (int) config('sanctum.remember_me_expiration_days', 30);

            $expiresAt = $isRememberMe
                ? now()->addDays($rememberMeTokenExpirationDays)
                : now()->addWeek();

            $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;
            $user->updateLastLogin($request->ip());

            Log::info('Utilisateur connecté', ['user_id' => $user->id]);

            return $this->successResponse([
                'user' => $user->makeHidden(['roles', 'permissions']),
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => now()->diffInSeconds($expiresAt),
                'expires_at' => $expiresAt->toISOString(),
                'remember_me' => $isRememberMe,
            ], 'Connexion réussie');

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Identifiants incorrects');

        } catch (\Exception $e) {
            Log::error('Erreur lors de la connexion', ['error' => $e->getMessage()]);
            return $this->errorResponse('Erreur lors de la connexion', $e->getMessage());
        }
    }

    /**
     * Normalise un numéro de téléphone au format international (+XXXXXXXXXXX).
     *
     * - "+33758855039"  → "+33758855039"  (déjà international, inchangé)
     * - "0033758855039" → "+33758855039"  (préfixe 00)
     * - "0758855039" + "+33" → "+33758855039"  (format local français)
     * - "0624000013" + "+224" → "+224624000013" (format local guinéen)
     */
    private function normalizePhone(string $phone, ?string $countryCode = null): string
    {
        $phone = trim($phone);

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (str_starts_with($phone, '00')) {
            return '+' . substr($phone, 2);
        }

        if (str_starts_with($phone, '0') && $countryCode) {
            return $countryCode . substr($phone, 1);
        }

        return $phone;
    }
}
