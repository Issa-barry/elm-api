<?php

use App\Http\Controllers\Vehicules\VehiculeDestroyController;
use App\Http\Controllers\Vehicules\VehiculeIndexController;
use App\Http\Controllers\Vehicules\VehiculeShowController;
use App\Http\Controllers\Vehicules\VehiculeStoreController;
use App\Http\Controllers\Vehicules\VehiculeUpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('vehicules')->group(function () {
    Route::get('/', VehiculeIndexController::class)->middleware('permission:vehicules.read');
    Route::post('/', VehiculeStoreController::class)->middleware('permission:vehicules.create');
    Route::get('/{id}', VehiculeShowController::class)->where('id', '[0-9]+')->middleware('permission:vehicules.read');
    Route::post('/{id}', VehiculeUpdateController::class)->where('id', '[0-9]+')->middleware('permission:vehicules.update');
    Route::delete('/{id}', VehiculeDestroyController::class)->where('id', '[0-9]+')->middleware('permission:vehicules.delete');
});
