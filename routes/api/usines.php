<?php

use App\Http\Controllers\Usine\UsineChangeDefaultController;
use App\Http\Controllers\Usine\UsineIndexController;
use App\Http\Controllers\Usine\UsinePatchController;
use App\Http\Controllers\Usine\UsineStoreController;
use App\Http\Controllers\Usine\UsineUserAffectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes API Usines
|--------------------------------------------------------------------------
|
| Gestion du parc d'usines et des affectations user ↔ usine.
| La création/modification est réservée aux utilisateurs siège.
|
*/

Route::prefix('usines')->group(function () {
    // Lecture — accessible à tous les staff authentifiés
    Route::get('/', UsineIndexController::class)->middleware('permission:usines.read');

    // Création — siège uniquement (vérification dans le controller)
    Route::post('/', UsineStoreController::class)->middleware('permission:usines.create');

    // Mise à jour partielle — siège uniquement (vérification dans le controller)
    Route::patch('/{id}', UsinePatchController::class)->where('id', '[0-9]+')->middleware('permission:usines.update');

    // Changer l'usine par défaut de l'utilisateur connecté
    Route::patch('/{id}/set-default', UsineChangeDefaultController::class)->where('id', '[0-9]+');

    // Affectation user ↔ usine — siège uniquement
    Route::post('/{usineId}/users', [UsineUserAffectController::class, 'attach'])
        ->where('usineId', '[0-9]+')
        ->middleware('permission:usines.update');

    Route::delete('/{usineId}/users/{userId}', [UsineUserAffectController::class, 'detach'])
        ->where(['usineId' => '[0-9]+', 'userId' => '[0-9]+'])
        ->middleware('permission:usines.update');
});
