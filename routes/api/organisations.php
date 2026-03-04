<?php

use App\Http\Controllers\Organisation\OrganisationDestroyController;
use App\Http\Controllers\Organisation\OrganisationIndexController;
use App\Http\Controllers\Organisation\OrganisationShowController;
use App\Http\Controllers\Organisation\OrganisationStoreController;
use App\Http\Controllers\Organisation\OrganisationUpdateController;
use Illuminate\Support\Facades\Route;

/**
 * Routes CRUD Organisation — accès super_admin uniquement.
 *
 * Toutes ces routes sont montées dans le groupe backoffice
 * (auth:sanctum + user.type:staff) défini dans routes/api.php.
 * Le middleware role:super_admin est appliqué ici localement.
 *
 * Note : pas de middleware usine.context — l'organisation est
 * un niveau au-dessus des usines.
 */
Route::middleware('role:super_admin')
    ->prefix('organisations')
    ->name('organisations.')
    ->group(function () {
        Route::get('/',                   OrganisationIndexController::class)->name('index');
        Route::post('/',                  OrganisationStoreController::class)->name('store');
        Route::get('/{organisation}',     OrganisationShowController::class)->name('show');
        Route::put('/{organisation}',     OrganisationUpdateController::class)->name('update');
        Route::delete('/{organisation}',  OrganisationDestroyController::class)->name('destroy');
    });
