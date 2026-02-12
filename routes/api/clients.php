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
    // Liste et consultation
    Route::get('/', ClientIndexController::class)->middleware('permission:clients.index');
    Route::get('/{id}', ClientShowController::class)->where('id', '[0-9]+')->middleware('permission:clients.show');

    // Création
    Route::post('/', ClientStoreController::class)->middleware('permission:clients.store');

    // Mise à jour et suppression
    Route::put('/{id}', ClientUpdateController::class)->where('id', '[0-9]+')->middleware('permission:clients.update');
    Route::delete('/{id}', ClientDestroyController::class)->where('id', '[0-9]+')->middleware('permission:clients.destroy');

    // Actions sur le statut
    Route::patch('/{id}/toggle-status', ClientToggleStatusController::class)->where('id', '[0-9]+')->middleware('permission:clients.toggle-status');
});
