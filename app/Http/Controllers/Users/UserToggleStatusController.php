<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserToggleStatusController extends Controller
{
    use ApiResponse;

    public function __invoke($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->notFoundResponse('Utilisateur non trouvé');
            }

            $user->update([
                'is_active' => !$user->is_active
            ]);

            $status = $user->is_active ? 'activé' : 'désactivé';

            Log::info("Utilisateur {$status}", ['user_id' => $id]);

            return $this->successResponse($user, "Utilisateur {$status} avec succès");
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors du changement de statut', $e->getMessage());
        }
    }
}
