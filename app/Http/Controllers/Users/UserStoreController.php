<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserStoreController extends Controller
{
    use ApiResponse;

    public function __invoke(StoreUserRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $data = $request->safe()->except(['role']);

                $user = User::create($data);

                // Assigner le rôle via Spatie (un seul rôle)
                $user->assignRole($request->validated('role'));

                $user->load('roles');

                return $this->createdResponse($user, 'Utilisateur créé avec succès');
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Erreur lors de la création de l\'utilisateur', $e->getMessage());
        }
    }
}
