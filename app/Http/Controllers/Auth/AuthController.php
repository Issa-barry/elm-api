<?php

namespace App\Http\Controllers\auth\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur
     */
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
                'nom.min' => 'Le nom doit contenir au moins 2 caractères',
                'prenom.required' => 'Le prénom est obligatoire',
                'prenom.min' => 'Le prénom doit contenir au moins 2 caractères',
                'phone.required' => 'Le numéro de téléphone est obligatoire',
                'phone.regex' => 'Le format du numéro de téléphone est invalide',
                'phone.unique' => 'Ce numéro de téléphone est déjà utilisé',
                'email.email' => 'Le format de l\'email est invalide',
                'email.unique' => 'Cet email est déjà utilisé',
                'code_pays.size' => 'Le code pays doit contenir 2 lettres (ex: GN, FR)',
                'code_pays.regex' => 'Le code pays doit être en majuscules',
                'code_phone_pays.regex' => 'Le code téléphonique doit commencer par + (ex: +224)',
                'ville.required' => 'La ville est obligatoire',
                'quartier.required' => 'Le quartier est obligatoire',
                'password.required' => 'Le mot de passe est obligatoire',
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

            // Créer un token avec expiration
            $token = $user->createToken(
                'auth_token',
                ['*'],
                now()->addMinutes(config('sanctum.expiration', 120))
            )->plainTextToken;

            // Enregistrer la première connexion
            $user->updateLastLogin($request->ip());

            DB::commit();

            Log::info('Nouvel utilisateur inscrit', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'reference' => $user->reference,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie',
                'data' => [
                    'user' => $user,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration', 120) * 60,
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur lors de l\'inscription', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);
        }
    }

    /**
     * Connexion
     */
    public function login(Request $request): JsonResponse
    {
        try {
            // Validation
            $validated = $request->validate([
                'identifier' => ['required', 'string'],
                'password' => ['required', 'string'],
                'remember_me' => ['nullable', 'boolean'],
            ], [
                'identifier.required' => 'Le téléphone ou l\'email est obligatoire',
                'password.required' => 'Le mot de passe est obligatoire',
            ]);

            // Déterminer si l'identifiant est un email ou un téléphone
            $fieldType = filter_var($validated['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
            
            $user = User::where($fieldType, $validated['identifier'])->first();

            // Vérifier les credentials
            if (!$user || !Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'identifier' => ['Les informations de connexion sont incorrectes.'],
                ]);
            }

            // Vérifier si le compte est actif
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte est désactivé. Veuillez contacter l\'administrateur.'
                ], 403);
            }

            // Créer un token avec expiration
            $expiresAt = ($validated['remember_me'] ?? false)
                ? now()->addDays(30)
                : now()->addMinutes(config('sanctum.expiration', 120));

            $token = $user->createToken(
                'auth_token',
                ['*'],
                $expiresAt
            )->plainTextToken;

            // Mettre à jour la dernière connexion
            $user->updateLastLogin($request->ip());

            Log::info('Utilisateur connecté', [
                'user_id' => $user->id,
                'phone' => $user->phone,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'data' => [
                    'user' => $user,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => $expiresAt->diffInSeconds(now()),
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la connexion', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);
        }
    }

    /**
     * Déconnexion (révoque le token actuel)
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            Log::info('Utilisateur déconnecté', [
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);
        }
    }

    /**
     * Déconnexion de tous les appareils
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $request->user()->tokens()->delete();

            Log::info('Utilisateur déconnecté de tous les appareils', [
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion de tous les appareils réussie'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);
        }
    }

    /**
     * Obtenir l'utilisateur connecté
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }

    /**
     * Mettre à jour le profil
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Validation
            $validated = $request->validate([
                'nom' => ['sometimes', 'string', 'min:2', 'max:100'],
                'prenom' => ['sometimes', 'string', 'min:2', 'max:100'],
                'email' => ['sometimes', 'nullable', 'email', 'max:255', 'unique:users,email,' . $user->id],
                'ville' => ['sometimes', 'string', 'min:2', 'max:100'],
                'quartier' => ['sometimes', 'string', 'min:2', 'max:100'],
            ], [
                'nom.min' => 'Le nom doit contenir au moins 2 caractères',
                'prenom.min' => 'Le prénom doit contenir au moins 2 caractères',
                'email.email' => 'Le format de l\'email est invalide',
                'email.unique' => 'Cet email est déjà utilisé',
                'ville.min' => 'La ville doit contenir au moins 2 caractères',
                'quartier.min' => 'Le quartier doit contenir au moins 2 caractères',
            ]);

            DB::beginTransaction();

            $user->update($validated);

            DB::commit();

            Log::info('Profil mis à jour', [
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => $user->fresh()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);
        }
    }

    /**
     * Changer le mot de passe
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            // Validation
            $validated = $request->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'new_password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()->mixedCase()],
            ], [
                'current_password.required' => 'Le mot de passe actuel est obligatoire',
                'current_password.current_password' => 'Le mot de passe actuel est incorrect',
                'new_password.required' => 'Le nouveau mot de passe est obligatoire',
                'new_password.confirmed' => 'Les mots de passe ne correspondent pas',
                'new_password.different' => 'Le nouveau mot de passe doit être différent de l\'ancien',
            ]);

            DB::beginTransaction();

            $user = $request->user();
            $user->update([
                'password' => $validated['new_password']
            ]);

            // Révoquer tous les autres tokens
            $currentTokenId = $user->currentAccessToken()->id;
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();

            DB::commit();

            Log::info('Mot de passe changé', [
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe modifié avec succès'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);
        }
    }

    /**
     * Rafraîchir le token
     */
    public function refreshToken(Request $request): JsonResponse
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

            return response()->json([
                'success' => true,
                'message' => 'Token rafraîchi avec succès',
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration', 120) * 60,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rafraîchissement du token',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue'
            ], 500);
        }
    }

    /**
     * Vérifier la validité du token
     */
    public function checkToken(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Token valide',
            'data' => [
                'valid' => true,
                'user' => $request->user()
            ]
        ]);
    }
}