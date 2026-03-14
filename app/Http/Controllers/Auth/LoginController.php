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
     * Normalise un numéro de téléphone au format E.164.
     * Même logique que NormalizesInputFields::normalizePhone.
     *
     * - "0758855039" + "+33"  → "+33758855039"
     * - "+330758855039" + "+33" → "+33758855039"  (0 redondant)
     * - "0033758855039"       → "+33758855039"  (préfixe 00)
     * - "+33758855039"        → "+33758855039"  (inchangé)
     * - "758855039" + "+33"   → "+33758855039"  (national sans 0)
     */
    private function normalizePhone(string $phone, ?string $countryCode = null): string
    {
        $v = preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';

        if (str_starts_with($v, '00')) {
            $v = '+' . substr($v, 2);
        }

        if ($countryCode !== null) {
            $prefix = preg_replace('/[^0-9+]/', '', $countryCode) ?? '';
            if ($prefix !== '') {
                if (str_starts_with($v, $prefix . '0')) {
                    return $prefix . substr($v, strlen($prefix) + 1);
                }
                if (str_starts_with($v, $prefix)) {
                    return $v;
                }
                if (str_starts_with($v, '0')) {
                    return $prefix . substr($v, 1);
                }
                return $prefix . $v;
            }
        }

        // Pas de code pays fourni mais numéro déjà international (+CC0XXXXXXX).
        // Le front peut concaténer +33 + 0654321987 → +330654321987.
        // Détecter et supprimer le 0 redondant : +{1-4 chiffres}0{6+ chiffres}.
        if (str_starts_with($v, '+')) {
            $v = (string) preg_replace('/^(\+\d{1,4})0(\d{6,})$/', '$1$2', $v);
        }

        return $v;
    }
}
