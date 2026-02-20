<?php

use App\Http\Controllers\Notification\NotificationIndexController;
use App\Http\Controllers\Notification\NotificationMarkReadController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')->group(function () {
    Route::get('/', NotificationIndexController::class);
    Route::post('/{id}/read', [NotificationMarkReadController::class, 'markOne']);
    Route::post('/read-all', [NotificationMarkReadController::class, 'markAll']);
});
