<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Prestataire\PrestataireIndexController;
use App\Http\Controllers\Prestataire\PrestataireShowController;
use App\Http\Controllers\Prestataire\PrestataireStoreController;
use App\Http\Controllers\Prestataire\PrestataireUpdateController;
use App\Http\Controllers\Prestataire\PrestataireDestroyController;
use App\Http\Controllers\Prestataire\PrestataireToggleStatusController;

/*
|--------------------------------------------------------------------------
| Routes API Prestataires
|--------------------------------------------------------------------------
|
| Gestion des prestataires externes (CRUD complet)
|
*/

Route::prefix('prestataires')->group(function () {
    // Lecture
    Route::get('/', PrestataireIndexController::class)->middleware('permission:prestataires.read');
    Route::get('/{id}', PrestataireShowController::class)->where('id', '[0-9]+')->middleware('permission:prestataires.read');

    // Création
    Route::post('/', PrestataireStoreController::class)->middleware('permission:prestataires.create');

    // Mise à jour
    Route::put('/{id}', PrestataireUpdateController::class)->where('id', '[0-9]+')->middleware('permission:prestataires.update');
    Route::patch('/{id}/toggle-status', PrestataireToggleStatusController::class)->where('id', '[0-9]+')->middleware('permission:prestataires.update');

    // Suppression
    Route::delete('/{id}', PrestataireDestroyController::class)->where('id', '[0-9]+')->middleware('permission:prestataires.delete');
});
