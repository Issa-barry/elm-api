<?php

use App\Http\Controllers\Livreurs\LivreurDestroyController;
use App\Http\Controllers\Livreurs\LivreurIndexController;
use App\Http\Controllers\Livreurs\LivreurShowController;
use App\Http\Controllers\Livreurs\LivreurStoreController;
use App\Http\Controllers\Livreurs\LivreurUpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('livreurs')->group(function () {
    Route::get('/', LivreurIndexController::class)->middleware('permission:livreurs.read');
    Route::post('/', LivreurStoreController::class)->middleware('permission:livreurs.create');
    Route::get('/{id}', LivreurShowController::class)->where('id', '[0-9]+')->middleware('permission:livreurs.read');
    Route::put('/{id}', LivreurUpdateController::class)->where('id', '[0-9]+')->middleware('permission:livreurs.update');
    Route::delete('/{id}', LivreurDestroyController::class)->where('id', '[0-9]+')->middleware('permission:livreurs.delete');
});
