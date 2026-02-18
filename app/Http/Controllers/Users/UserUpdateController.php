<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserUpdateController extends Controller
{
    use ApiResponse;

    public function __invoke(UpdateUserRequest $request, $id)
    {
        try {
            $user = User::find($id);

            if (! $user) {
                return $this->notFoundResponse('Utilisateur non trouvé');
            }

            return DB::transaction(function () use ($request, $user) {
                $data = $request->safe()->except(['role']);

                $user->update($data);

                // Si un rôle est fourni, synchroniser (remplace l'ancien)
                if ($request->has('role')) {
                    $user->syncRoles([$request->validated('role')]);
                }

                $user->load('roles');

                return $this->successResponse($user, 'Utilisateur mis à jour avec succès');
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la mise à jour de l\'utilisateur', $e->getMessage());
        }
    }
}
