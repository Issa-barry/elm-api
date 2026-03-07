<?php

use App\Http\Controllers\Dashboard\DashboardStatsController;
use App\Http\Controllers\Dashboard\VenteEncaissementStatController;
use App\Http\Controllers\Dashboard\VenteEvolutionStatutFactureController;
use App\Http\Controllers\Dashboard\VenteEvolutionTypeVehiculeController;
use App\Http\Controllers\Dashboard\VenteStatTypeVehiculeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes API Dashboard
|--------------------------------------------------------------------------
|
| Statistiques agrégées pour le tableau de bord.
|
*/

Route::prefix('dashboard')->group(function () {
    Route::get('/stats', DashboardStatsController::class)
        ->middleware('permission:prestataires.read');

    // ── Statistiques ventes ────────────────────────────────────────────────
    Route::prefix('ventes')->group(function () {
        Route::get('/par-type-vehicule', VenteStatTypeVehiculeController::class)
            ->middleware('permission:commandes.read');
        Route::get('/encaissements', VenteEncaissementStatController::class)
            ->middleware('permission:commandes.read');
        Route::get('/evolution-par-type', VenteEvolutionTypeVehiculeController::class)
            ->middleware('permission:commandes.read');
        Route::get('/evolution-par-statut', VenteEvolutionStatutFactureController::class)
            ->middleware('permission:commandes.read');
    });
});
