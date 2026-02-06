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
            // Validation
            $validated = $request->validate([
                'nom' => ['required', 'string', 'min:2', 'max:100'],
                'prenom' => ['required', 'string', 'min:2', 'max:100'],
                'phone' => ['required', 'string', 'regex:/^[\+]?[0-9]{8,15}$/', 'unique:users,phone'],
                'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
                'pays' => ['required', 'string', 'max:100'],
                'code_pays' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
                'code_phone_pays' => ['required', 'string', 'regex:/^\+[0-9]{1,4}$/'],
                'ville' => ['required', 'string', 'min:2', 'max:100'],
                'quartier' => ['required', 'string', 'min:2', 'max:100'],
                'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->mixedCase()],
            ], [
                'nom.required' => 'Le nom est obligatoire',
                'prenom.required' => 'Le prénom est obligatoire',
                'phone.required' => 'Le numéro de téléphone est obligatoire',
                'phone.unique' => 'Ce numéro de téléphone est déjà utilisé',
                'email.unique' => 'Cette adresse email est déjà utilisée',
                'password.confirmed' => 'Les mots de passe ne correspondent pas',
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

            // Créer un token
            $token = $user->createToken(
                'auth_token',
                ['*'],
                now()->addMinutes(config('sanctum.expiration', 120))
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
                'expires_in' => config('sanctum.expiration', 120) * 60,
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
}