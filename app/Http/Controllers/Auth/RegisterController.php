<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    use ApiResponse;

    public function register(Request $request): JsonResponse
    {
        try {
            $request->merge([
                'email' => $this->normalizeEmail($request->input('email')),
                'ville' => $this->normalizeLocation($request->input('ville')),
                'quartier' => $this->normalizeLocation($request->input('quartier')),
            ]);

            // Validation
            $validated = $request->validate([
                'nom' => ['required', 'string', 'min:2', 'max:100'],
                'prenom' => ['required', 'string', 'min:2', 'max:100'],
                'phone' => ['required', 'string', 'regex:/^[\+]?[0-9]{8,15}$/', 'unique:users,phone'],
                'email' => ['nullable', 'email:rfc,dns', 'max:255', 'unique:users,email'],
                'pays' => ['required', 'string', 'max:100'],
                'code_pays' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
                'code_phone_pays' => ['required', 'string', 'regex:/^\+[0-9]{1,4}$/'],
                'ville' => ['required', 'string', 'min:2', 'max:100'],
                'quartier' => ['required', 'string', 'min:2', 'max:100'],
                'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->mixedCase()],
                'role' => ['required', 'string', 'exists:roles,name'],
            ], [
                'nom.required' => 'Le nom est obligatoire',
                'prenom.required' => 'Le prénom est obligatoire',
                'phone.required' => 'Le numéro de téléphone est obligatoire',
                'phone.unique' => 'Ce numéro de téléphone est déjà utilisé',
                'email.email' => 'Le format de l\'adresse email est invalide',
                'email.unique' => 'Cette adresse email est déjà utilisée',
                'password.confirmed' => 'Les mots de passe ne correspondent pas',
                'role.required' => 'Le rôle est obligatoire',
                'role.exists' => 'Ce rôle n\'existe pas',
            ]);

            DB::beginTransaction();

            // Créer l'utilisateur
            $user = User::create([
                'nom' => $validated['nom'],
                'prenom' => $validated['prenom'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'pays' => $validated['pays'],
                'code_pays' => strtoupper($validated['code_pays']),
                'code_phone_pays' => $validated['code_phone_pays'],
                'ville' => $validated['ville'],
                'quartier' => $validated['quartier'],
                'password' => $validated['password'],
            ]);

            // Assigner le rôle
            $user->assignRole($validated['role']);

            // Créer un token
            $defaultTokenExpiration = (int) config('sanctum.default_expiration', 120);
            $expiresAt = now()->addMinutes($defaultTokenExpiration);

            $token = $user->createToken(
                'auth_token',
                ['*'],
                $expiresAt
            )->plainTextToken;

            $user->updateLastLogin($request->ip());

            DB::commit();

            Log::info('Nouvel utilisateur inscrit', [
                'user_id' => $user->id,
                'reference' => $user->reference,
            ]);

            return $this->createdResponse([
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => now()->diffInSeconds($expiresAt),
                'expires_at' => $expiresAt->toISOString(),
            ], 'Inscription réussie');

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e->errors(), 'Erreur de validation');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur lors de l\'inscription', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors de l\'inscription',
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    private function normalizeEmail($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : strtolower($normalized);
    }

    private function normalizeLocation($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
    }
}
