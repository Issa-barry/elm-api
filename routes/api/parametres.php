<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Parametre\ParametreIndexController;
use App\Http\Controllers\Parametre\ParametreUpdateController;
use App\Http\Controllers\Parametre\ParametrePeriodesController;

/*
|--------------------------------------------------------------------------
| Routes API Paramètres
|--------------------------------------------------------------------------
|
| Gestion des paramètres de l'application
|
*/

Route::prefix('parametres')->group(function () {
    // Liste des paramètres
    Route::get('/', ParametreIndexController::class);

    // Récupérer les périodes (pour le formulaire de paiement)
    Route::get('/periodes', ParametrePeriodesController::class);

    // Modifier un paramètre
    Route::put('/{id}', ParametreUpdateController::class)->where('id', '[0-9]+');
});
