<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Users\UserIndexController;
use App\Http\Controllers\Users\UserStoreController;
use App\Http\Controllers\Users\UserShowController;
use App\Http\Controllers\Users\UserUpdateController;
use App\Http\Controllers\Users\UserDestroyController;
use App\Http\Controllers\Users\UserToggleStatusController;
use App\Http\Controllers\Users\UserCheckPhoneController;

/*
|--------------------------------------------------------------------------
| Routes API Users
|--------------------------------------------------------------------------
|
| Gestion des utilisateurs (CRUD sans création - gérée par auth/register)
|
*/

Route::prefix('users')->group(function () {
    // Vérification disponibilité téléphone (étape 1 création)
    Route::post('/check-phone', UserCheckPhoneController::class)->middleware('permission:users.create');

    // Création
    Route::post('/', UserStoreController::class)->middleware('permission:users.create');

    // Lecture
    Route::get('/', UserIndexController::class)->middleware('permission:users.read');
    Route::get('/{id}', UserShowController::class)->where('id', '[0-9]+')->middleware('permission:users.read');

    // Mise à jour
    Route::put('/{id}', UserUpdateController::class)->where('id', '[0-9]+')->middleware('permission:users.update');
    Route::patch('/{id}/toggle-status', UserToggleStatusController::class)->where('id', '[0-9]+')->middleware('permission:users.update');

    // Suppression
    Route::delete('/{id}', UserDestroyController::class)->where('id', '[0-9]+')->middleware('permission:users.delete');
});
