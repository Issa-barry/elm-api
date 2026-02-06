<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Users\UserIndexController;
use App\Http\Controllers\Users\UserShowController;
use App\Http\Controllers\Users\UserUpdateController;
use App\Http\Controllers\Users\UserDestroyController;
use App\Http\Controllers\Users\UserToggleStatusController;

/*
|--------------------------------------------------------------------------
| Routes API Users
|--------------------------------------------------------------------------
|
| Gestion des utilisateurs (CRUD sans création - gérée par auth/register)
|
*/

Route::prefix('users')->group(function () {
    // Liste et consultation
    Route::get('/', UserIndexController::class);
    Route::get('/{id}', UserShowController::class)->where('id', '[0-9]+');

    // Mise à jour et suppression
    Route::put('/{id}', UserUpdateController::class)->where('id', '[0-9]+');
    Route::delete('/{id}', UserDestroyController::class)->where('id', '[0-9]+');

    // Actions sur le statut
    Route::patch('/{id}/toggle-status', UserToggleStatusController::class)->where('id', '[0-9]+');
});
