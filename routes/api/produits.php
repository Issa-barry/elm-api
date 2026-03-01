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
use App\Http\Controllers\Produit\ProduitUploadImageController;
use App\Http\Controllers\Produit\ProduitDeleteImageController;
use App\Http\Controllers\Produit\ProduitPosController;
use App\Http\Controllers\Produit\ProduitUsineAffecterController;
use App\Http\Controllers\Produit\ProduitUsineDesaffecterController;
use App\Http\Controllers\Produit\ProduitUsineActiverController;
use App\Http\Controllers\Produit\ProduitUsineDesactiverController;
use App\Http\Controllers\Produit\ProduitUsinePrixController;

/*
|--------------------------------------------------------------------------
| Routes API Produits
|--------------------------------------------------------------------------
|
| Types: materiel, service, fabricable, achat_vente
| Statuts: brouillon, actif, inactif, archive
|
*/

Route::prefix('produits')->group(function () {

    // ── Lecture ──────────────────────────────────────────────────────────
    Route::get('/', ProduitIndexController::class)->middleware('permission:produits.read');
    Route::get('/archived', ProduitArchivedListController::class)->middleware('permission:produits.read');
    Route::get('/search', ProduitSearchController::class)->middleware('permission:produits.read');
    Route::get('/statistics', ProduitStatisticsController::class)->middleware('permission:produits.read');

    // Catalogue POS : produits actifs localement dans l'usine courante
    Route::get('/pos', ProduitPosController::class)->middleware('permission:produits.read');

    Route::get('/{id}', ProduitShowController::class)->where('id', '[0-9]+')->middleware('permission:produits.read');

    // ── Création ─────────────────────────────────────────────────────────
    Route::post('/', ProduitStoreController::class)->middleware('permission:produits.create');

    // ── Mise à jour globale ───────────────────────────────────────────────
    Route::put('/{id}', ProduitUpdateController::class)->where('id', '[0-9]+')->middleware('permission:produits.update');
    Route::patch('/{id}/stock', ProduitUpdateStockController::class)->where('id', '[0-9]+')->middleware('permission:produits.update');
    Route::patch('/{id}/status', ProduitChangeStatusController::class)->where('id', '[0-9]+')->middleware('permission:produits.update');
    Route::patch('/{id}/archive', ProduitArchiveController::class)->where('id', '[0-9]+')->middleware('permission:produits.update');
    Route::patch('/{id}/unarchive', ProduitUnarchiveController::class)->where('id', '[0-9]+')->middleware('permission:produits.update');

    // ── Config locale par usine (produit_usines) ──────────────────────────
    Route::prefix('/{id}/usines')->where(['id' => '[0-9]+'])->group(function () {
        Route::post('/', ProduitUsineAffecterController::class)->middleware('permission:produits.update');
        Route::delete('/{usine_id}', ProduitUsineDesaffecterController::class)
            ->where('usine_id', '[0-9]+')
            ->middleware('permission:produits.update');

        Route::patch('/{usine_id}/activer', ProduitUsineActiverController::class)
            ->where('usine_id', '[0-9]+')
            ->middleware('permission:produits.update');
        Route::patch('/{usine_id}/desactiver', ProduitUsineDesactiverController::class)
            ->where('usine_id', '[0-9]+')
            ->middleware('permission:produits.update');
        Route::patch('/{usine_id}/prix', ProduitUsinePrixController::class)
            ->where('usine_id', '[0-9]+')
            ->middleware('permission:produits.update');
    });

    // ── Image ─────────────────────────────────────────────────────────────
    Route::post('/{id}/image', ProduitUploadImageController::class)->where('id', '[0-9]+')->middleware('permission:produits.update');
    Route::delete('/{id}/image', ProduitDeleteImageController::class)->where('id', '[0-9]+')->middleware('permission:produits.update');

    // ── Suppression ───────────────────────────────────────────────────────
    Route::delete('/{id}', ProduitDestroyController::class)->where('id', '[0-9]+')->middleware('permission:produits.delete');
});
