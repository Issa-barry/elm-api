<?php

use App\Http\Controllers\Billing\BillingEventIndexController;
use App\Http\Controllers\Billing\BillingEventMarkPaidController;
use App\Http\Controllers\Billing\ForfaitDestroyController;
use App\Http\Controllers\Billing\ForfaitIndexController;
use App\Http\Controllers\Billing\ForfaitStoreController;
use App\Http\Controllers\Billing\ForfaitUpdateController;
use Illuminate\Support\Facades\Route;

/**
 * Routes facturation — accès super_admin uniquement.
 *
 * Montées dans le groupe backoffice (auth:sanctum + user.type:staff)
 * défini dans routes/api.php.
 */
Route::middleware('role:super_admin')
    ->prefix('billing')
    ->name('billing.')
    ->group(function () {
        Route::get('/events',                BillingEventIndexController::class)->name('events.index');
        Route::patch('/events/{event}/paid', BillingEventMarkPaidController::class)->name('events.mark-paid');

        Route::get('/forfaits',              ForfaitIndexController::class)->name('forfaits.index');
        Route::post('/forfaits',             ForfaitStoreController::class)->name('forfaits.store');
        Route::put('/forfaits/{forfait}',    ForfaitUpdateController::class)->name('forfaits.update');
        Route::delete('/forfaits/{forfait}', ForfaitDestroyController::class)->name('forfaits.destroy');
    });
