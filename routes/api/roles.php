<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Role\RoleIndexController;
use App\Http\Controllers\Role\RoleShowController;
use App\Http\Controllers\Role\RoleStoreController;
use App\Http\Controllers\Role\RoleUpdateController;
use App\Http\Controllers\Role\RoleDestroyController;
use App\Http\Controllers\Role\RoleUpdatePermissionsController;
use App\Http\Controllers\Role\AssignRoleController;
use App\Http\Controllers\Role\PermissionIndexController;
use App\Http\Controllers\Role\UserRolesController;

/*
|--------------------------------------------------------------------------
| Routes API Rôles et Permissions
|--------------------------------------------------------------------------
|
| Gestion des rôles et permissions (admin uniquement)
| Style Strapi : matrice modules × CRUD
|
*/

Route::middleware('role:admin')->group(function () {
    // Rôles CRUD
    Route::prefix('roles')->group(function () {
        Route::get('/', RoleIndexController::class);
        Route::post('/', RoleStoreController::class);
        Route::get('/{id}', RoleShowController::class)->where('id', '[0-9]+');
        Route::put('/{id}', RoleUpdateController::class)->where('id', '[0-9]+');
        Route::delete('/{id}', RoleDestroyController::class)->where('id', '[0-9]+');

        // Matrice permissions d'un rôle
        Route::put('/{id}/permissions', RoleUpdatePermissionsController::class)->where('id', '[0-9]+');

        // Assigner un rôle à un utilisateur
        Route::post('/assign/{userId}', AssignRoleController::class)->where('userId', '[0-9]+');
    });

    // Liste des permissions (matrice vide pour nouveau rôle)
    Route::get('/permissions', PermissionIndexController::class);

    // Rôles d'un utilisateur
    Route::get('/users/{userId}/roles', UserRolesController::class)->where('userId', '[0-9]+');
});
