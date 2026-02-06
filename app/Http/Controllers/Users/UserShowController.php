<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;

class UserShowController extends Controller
{
    use ApiResponse;

    public function __invoke($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->notFoundResponse('Utilisateur non trouvé');
            }

            return $this->successResponse($user, 'Utilisateur récupéré avec succès');
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la récupération de l\'utilisateur', $e->getMessage());
        }
    }
}
