<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Role\RoleIndexController;
use App\Http\Controllers\Role\RoleShowController;
use App\Http\Controllers\Role\AssignRoleController;
use App\Http\Controllers\Role\PermissionIndexController;
use App\Http\Controllers\Role\UserRolesController;

/*
|--------------------------------------------------------------------------
| Routes API Rôles et Permissions
|--------------------------------------------------------------------------
|
| Gestion des rôles et permissions (admin uniquement)
|
*/

// Route::middleware('role:admin')->group(function () {
    // Rôles
    Route::prefix('roles')->group(function () {
        Route::get('/', RoleIndexController::class);
        Route::get('/{id}', RoleShowController::class)->where('id', '[0-9]+');
        Route::post('/assign/{userId}', AssignRoleController::class)->where('userId', '[0-9]+');
    // });

    // Permissions
    Route::get('/permissions', PermissionIndexController::class);

    // Rôles d'un utilisateur
    Route::get('/users/{userId}/roles', UserRolesController::class)->where('userId', '[0-9]+');
});
