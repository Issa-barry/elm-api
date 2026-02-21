<?php

use App\Http\Controllers\Livraisons\CommissionCalculController;
use App\Http\Controllers\Livraisons\DeductionCommissionStoreController;
use App\Http\Controllers\Livraisons\EncaissementIndexController;
use App\Http\Controllers\Livraisons\EncaissementStoreController;
use App\Http\Controllers\Livraisons\FactureLivraisonIndexController;
use App\Http\Controllers\Livraisons\FactureLivraisonShowController;
use App\Http\Controllers\Livraisons\FactureLivraisonStoreController;
use App\Http\Controllers\Livraisons\PaiementCommissionStoreController;
use App\Http\Controllers\Livraisons\SortieVehiculeClotureController;
use App\Http\Controllers\Livraisons\SortieVehiculeIndexController;
use App\Http\Controllers\Livraisons\SortieVehiculeRetourController;
use App\Http\Controllers\Livraisons\SortieVehiculeShowController;
use App\Http\Controllers\Livraisons\SortieVehiculeStoreController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes Livraisons
|--------------------------------------------------------------------------
| Sorties véhicules, factures, encaissements, commissions
*/

// ── Sorties véhicules ────────────────────────────────────────────────────
Route::prefix('sorties-vehicules')->group(function () {
    Route::get('/', SortieVehiculeIndexController::class)->middleware('permission:sorties.read');
    Route::post('/', SortieVehiculeStoreController::class)->middleware('permission:sorties.create');
    Route::get('/{id}', SortieVehiculeShowController::class)->where('id', '[0-9]+')->middleware('permission:sorties.read');
    Route::patch('/{id}/retour', SortieVehiculeRetourController::class)->where('id', '[0-9]+')->middleware('permission:sorties.update');
    Route::patch('/{id}/cloture', SortieVehiculeClotureController::class)->where('id', '[0-9]+')->middleware('permission:sorties.update');
});

// ── Factures de livraison ────────────────────────────────────────────────
Route::prefix('factures-livraisons')->group(function () {
    Route::get('/', FactureLivraisonIndexController::class)->middleware('permission:factures-livraisons.read');
    Route::post('/', FactureLivraisonStoreController::class)->middleware('permission:factures-livraisons.create');
    Route::get('/{id}', FactureLivraisonShowController::class)->where('id', '[0-9]+')->middleware('permission:factures-livraisons.read');
});

// ── Encaissements ────────────────────────────────────────────────────────
Route::prefix('encaissements-livraisons')->group(function () {
    Route::get('/', EncaissementIndexController::class)->middleware('permission:encaissements.read');
    Route::post('/', EncaissementStoreController::class)->middleware('permission:encaissements.create');
});

// ── Déductions de commission ─────────────────────────────────────────────
Route::prefix('deductions-commissions')->group(function () {
    Route::post('/', DeductionCommissionStoreController::class)->middleware('permission:commissions.create');
});

// ── Commissions ──────────────────────────────────────────────────────────
Route::prefix('commissions')->group(function () {
    Route::get('/{sortieId}/calcul', CommissionCalculController::class)->where('sortieId', '[0-9]+')->middleware('permission:commissions.read');
    Route::post('/{sortieId}/paiement', PaiementCommissionStoreController::class)->where('sortieId', '[0-9]+')->middleware('permission:commissions.create');
});
