<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Parametre\ParametreIndexController;
use App\Http\Controllers\Parametre\ParametreUpdateController;

/*
|--------------------------------------------------------------------------
| Routes API Paramètres
|--------------------------------------------------------------------------
|
| Gestion des paramètres de l'application
|
*/

Route::prefix('parametres')->group(function () {
    // Lecture
    Route::get('/', ParametreIndexController::class)->middleware('permission:parametres.read');

    // Mise à jour
    Route::put('/{id}', ParametreUpdateController::class)->where('id', '[0-9]+')->middleware('permission:parametres.update');
});
