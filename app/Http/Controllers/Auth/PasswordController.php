<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    use ApiResponse;

    public function change(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'new_password' => [
                    'required',
                    'confirmed',
                    'different:current_password',
                    Password::min(8)->letters()->numbers()->mixedCase(),
                ],
            ], [
                'current_password.required' => 'Le mot de passe actuel est obligatoire',
                'current_password.current_password' => 'Le mot de passe actuel est incorrect',
                'new_password.required' => 'Le nouveau mot de passe est obligatoire',
                'new_password.confirmed' => 'Les mots de passe ne correspondent pas',
                'new_password.different' => 'Le nouveau mot de passe doit être différent de l\'ancien',
            ]);

            DB::beginTransaction();

            $user = $request->user();

            // 1) Hash obligatoire
            $user->update([
                'password' => Hash::make($validated['new_password']),
            ]);

            // 2) Révoquer les autres tokens : null-safe
            $currentToken = $user->currentAccessToken(); // peut être null
            if ($currentToken) {
                $user->tokens()->where('id', '!=', $currentToken->id)->delete();
            }

            DB::commit();

            Log::info('Mot de passe changé', ['user_id' => $user->id]);

            return $this->successResponse(null, 'Mot de passe modifié avec succès');

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e->errors(), 'Erreur de validation');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Erreur lors du changement de mot de passe', [
                'user_id' => optional($request->user())->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors du changement de mot de passe',
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }
}