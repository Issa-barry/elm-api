<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Packing\PackingIndexController;
use App\Http\Controllers\Packing\PackingShowController;
use App\Http\Controllers\Packing\PackingStoreController;
use App\Http\Controllers\Packing\PackingUpdateController;
use App\Http\Controllers\Packing\PackingDestroyController;
use App\Http\Controllers\Packing\PackingChangeStatutController;
use App\Http\Controllers\Packing\PackingValiderController;

/*
|--------------------------------------------------------------------------
| Routes API Packings
|--------------------------------------------------------------------------
|
| Gestion des packings (machinistes - rouleaux)
|
*/

Route::prefix('packings')->group(function () {
    // Lecture
    Route::get('/', PackingIndexController::class)->middleware('permission:packings.read');
    Route::get('/{id}', PackingShowController::class)->where('id', '[0-9]+')->middleware('permission:packings.read');

    // Création
    Route::post('/', PackingStoreController::class)->middleware('permission:packings.create');
    Route::post('/{id}/valider', PackingValiderController::class)->where('id', '[0-9]+')->middleware('permission:packings.create');

    // Mise à jour
    Route::put('/{id}', PackingUpdateController::class)->where('id', '[0-9]+')->middleware('permission:packings.update');
    Route::patch('/{id}/statut', PackingChangeStatutController::class)->where('id', '[0-9]+')->middleware('permission:packings.update');

    // Suppression
    Route::delete('/{id}', PackingDestroyController::class)->where('id', '[0-9]+')->middleware('permission:packings.delete');
});
