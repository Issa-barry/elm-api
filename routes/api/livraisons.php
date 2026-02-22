<?php

use App\Http\Controllers\Livraisons\CommissionCalculController;
use App\Http\Controllers\Livraisons\CommissionFactureCalculController;
use App\Http\Controllers\Livraisons\DeductionCommissionStoreController;
use App\Http\Controllers\Livraisons\DeductionFactureStoreController;
use App\Http\Controllers\Livraisons\EncaissementIndexController;
use App\Http\Controllers\Livraisons\EncaissementStoreController;
use App\Http\Controllers\Livraisons\FactureLivraisonIndexController;
use App\Http\Controllers\Livraisons\FactureLivraisonShowController;
use App\Http\Controllers\Livraisons\FactureLivraisonStoreController;
use App\Http\Controllers\Livraisons\FactureSimplifieeIndexController;
use App\Http\Controllers\Livraisons\FactureSimplifieeShowController;
use App\Http\Controllers\Livraisons\FactureSimplifieeStoreController;
use App\Http\Controllers\Livraisons\PaiementCommissionFactureStoreController;
use App\Http\Controllers\Livraisons\PaiementCommissionStoreController;
use App\Http\Controllers\Livraisons\SortieVehiculeClotureController;
use App\Http\Controllers\Livraisons\SortieVehiculeIndexController;
use App\Http\Controllers\Livraisons\SortieVehiculeRetourController;
use App\Http\Controllers\Livraisons\SortieVehiculeShowController;
use App\Http\Controllers\Livraisons\SortieVehiculeStoreController;
use App\Http\Controllers\Livraisons\VehiculeOneShotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes Livraisons
|--------------------------------------------------------------------------
| Workflow simplifié : one-shot, factures directes, commissions par facture
| Workflow classique : sorties véhicules, factures, encaissements (conservé)
*/

// ═══════════════════════════════════════════════════════════════════════════
//  WORKFLOW SIMPLIFIÉ
// ═══════════════════════════════════════════════════════════════════════════

// ── Création one-shot : véhicule + propriétaire + livreur ─────────────────
Route::post('/livraisons/one-shot', VehiculeOneShotController::class)
    ->middleware('permission:vehicules.create');

// ── Factures de livraison (liées directement au véhicule) ─────────────────
Route::prefix('livraisons/factures')->group(function () {
    Route::get('/', FactureSimplifieeIndexController::class)->middleware('permission:factures-livraisons.read');
    Route::post('/', FactureSimplifieeStoreController::class)->middleware('permission:factures-livraisons.create');
    Route::get('/{id}', FactureSimplifieeShowController::class)->where('id', '[0-9]+')->middleware('permission:factures-livraisons.read');

    // Déductions par facture
    Route::post('/{factureId}/deductions', DeductionFactureStoreController::class)
        ->where('factureId', '[0-9]+')
        ->middleware('permission:commissions.create');

    // Commission : calcul et paiement
    Route::get('/{factureId}/commissions/calcul', CommissionFactureCalculController::class)
        ->where('factureId', '[0-9]+')
        ->middleware('permission:commissions.read');
    Route::post('/{factureId}/commissions/paiement', PaiementCommissionFactureStoreController::class)
        ->where('factureId', '[0-9]+')
        ->middleware('permission:commissions.create');
});

// ═══════════════════════════════════════════════════════════════════════════
//  WORKFLOW CLASSIQUE (conservé pour rétrocompatibilité)
// ═══════════════════════════════════════════════════════════════════════════

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
