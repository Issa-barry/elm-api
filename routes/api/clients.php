<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Client\ClientIndexController;
use App\Http\Controllers\Client\ClientShowController;
use App\Http\Controllers\Client\ClientStoreController;
use App\Http\Controllers\Client\ClientUpdateController;
use App\Http\Controllers\Client\ClientDestroyController;
use App\Http\Controllers\Client\ClientToggleStatusController;

/*
|--------------------------------------------------------------------------
| Routes API Clients
|--------------------------------------------------------------------------
|
| Gestion des clients (CRUD complet)
|
*/

Route::prefix('clients')->group(function () {
    // Lecture
    Route::get('/', ClientIndexController::class)->middleware('permission:clients.read');
    Route::get('/{id}', ClientShowController::class)->where('id', '[0-9]+')->middleware('permission:clients.read');

    // Création
    Route::post('/', ClientStoreController::class)->middleware('permission:clients.create');

    // Mise à jour
    Route::put('/{id}', ClientUpdateController::class)->where('id', '[0-9]+')->middleware('permission:clients.update');
    Route::patch('/{id}/toggle-status', ClientToggleStatusController::class)->where('id', '[0-9]+')->middleware('permission:clients.update');

    // Suppression
    Route::delete('/{id}', ClientDestroyController::class)->where('id', '[0-9]+')->middleware('permission:clients.delete');
});
