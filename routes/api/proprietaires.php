<?php

use App\Http\Controllers\Proprietaires\ProprietaireDestroyController;
use App\Http\Controllers\Proprietaires\ProprietaireIndexController;
use App\Http\Controllers\Proprietaires\ProprietaireShowController;
use App\Http\Controllers\Proprietaires\ProprietaireStoreController;
use App\Http\Controllers\Proprietaires\ProprietaireUpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('proprietaires')->group(function () {
    Route::get('/', ProprietaireIndexController::class)->middleware('permission:proprietaires.read');
    Route::post('/', ProprietaireStoreController::class)->middleware('permission:proprietaires.create');
    Route::get('/{id}', ProprietaireShowController::class)->where('id', '[0-9]+')->middleware('permission:proprietaires.read');
    Route::put('/{id}', ProprietaireUpdateController::class)->where('id', '[0-9]+')->middleware('permission:proprietaires.update');
    Route::delete('/{id}', ProprietaireDestroyController::class)->where('id', '[0-9]+')->middleware('permission:proprietaires.delete');
});
