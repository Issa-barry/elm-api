<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Produit\ProduitIndexController;
use App\Http\Controllers\Produit\ProduitShowController;
use App\Http\Controllers\Produit\ProduitStoreController;
use App\Http\Controllers\Produit\ProduitUpdateController;
use App\Http\Controllers\Produit\ProduitDestroyController;
use App\Http\Controllers\Produit\ProduitSearchController;
use App\Http\Controllers\Produit\ProduitUpdateStockController;
use App\Http\Controllers\Produit\ProduitChangeStatusController;
use App\Http\Controllers\Produit\ProduitStatisticsController;
use App\Http\Controllers\Produit\ProduitArchiveController;
use App\Http\Controllers\Produit\ProduitUnarchiveController;
use App\Http\Controllers\Produit\ProduitArchivedListController;

/*
|--------------------------------------------------------------------------
| Routes API Produits
|--------------------------------------------------------------------------
|
| Types: materiel, service, fabricable, achat_vente
| Statuts: brouillon, actif, inactif, archive, rupture_stock
|
*/

Route::prefix('produits')->group(function () {
    // Liste et consultation
    Route::get('/', ProduitIndexController::class)->middleware('permission:produits.index');
    Route::get('/archived', ProduitArchivedListController::class)->middleware('permission:produits.archived-list');
    Route::get('/search', ProduitSearchController::class)->middleware('permission:produits.search');
    Route::get('/statistics', ProduitStatisticsController::class)->middleware('permission:produits.statistics');
    Route::get('/{id}', ProduitShowController::class)->where('id', '[0-9]+')->middleware('permission:produits.show');

    // CRUD
    Route::post('/', ProduitStoreController::class)->middleware('permission:produits.store');
    Route::put('/{id}', ProduitUpdateController::class)->where('id', '[0-9]+')->middleware('permission:produits.update');
    Route::delete('/{id}', ProduitDestroyController::class)->where('id', '[0-9]+')->middleware('permission:produits.destroy');

    // Actions sur le stock
    Route::patch('/{id}/stock', ProduitUpdateStockController::class)->where('id', '[0-9]+')->middleware('permission:produits.update-stock');

    // Actions sur le statut
    Route::patch('/{id}/status', ProduitChangeStatusController::class)->where('id', '[0-9]+')->middleware('permission:produits.change-status');
    Route::patch('/{id}/archive', ProduitArchiveController::class)->where('id', '[0-9]+')->middleware('permission:produits.archive');
    Route::patch('/{id}/unarchive', ProduitUnarchiveController::class)->where('id', '[0-9]+')->middleware('permission:produits.unarchive');
});
