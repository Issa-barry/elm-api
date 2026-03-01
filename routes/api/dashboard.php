<?php

use App\Http\Controllers\Dashboard\DashboardStatsController;
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
});
