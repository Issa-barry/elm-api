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
    // Liste et consultation
    Route::get('/', PrestataireIndexController::class);
    Route::get('/{id}', PrestataireShowController::class)->where('id', '[0-9]+');

    // Création
    Route::post('/', PrestataireStoreController::class);

    // Mise à jour et suppression
    Route::put('/{id}', PrestataireUpdateController::class)->where('id', '[0-9]+');
    Route::delete('/{id}', PrestataireDestroyController::class)->where('id', '[0-9]+');

    // Actions sur le statut
    Route::patch('/{id}/toggle-status', PrestataireToggleStatusController::class)->where('id', '[0-9]+');
});
