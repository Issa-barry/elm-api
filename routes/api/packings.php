<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Packing\PackingIndexController;
use App\Http\Controllers\Packing\PackingShowController;
use App\Http\Controllers\Packing\PackingStoreController;
use App\Http\Controllers\Packing\PackingUpdateController;
use App\Http\Controllers\Packing\PackingDestroyController;
use App\Http\Controllers\Packing\PackingChangeStatutController;

/*
|--------------------------------------------------------------------------
| Routes API Packings
|--------------------------------------------------------------------------
|
| Gestion des packings (machinistes - rouleaux)
|
*/

Route::prefix('packings')->group(function () {
    // Liste et consultation
    Route::get('/', PackingIndexController::class);
    Route::get('/{id}', PackingShowController::class)->where('id', '[0-9]+');

    // Création
    Route::post('/', PackingStoreController::class);

    // Mise à jour et suppression
    Route::put('/{id}', PackingUpdateController::class)->where('id', '[0-9]+');
    Route::delete('/{id}', PackingDestroyController::class)->where('id', '[0-9]+');

    // Changement de statut
    Route::patch('/{id}/statut', PackingChangeStatutController::class)->where('id', '[0-9]+');
});
