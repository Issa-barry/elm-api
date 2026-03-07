<?php

use App\Http\Controllers\Livraisons\VehiculeOneShotController;
use App\Http\Controllers\Ventes\CommandeVenteAnnulerController;
use App\Http\Controllers\Ventes\CommandeVenteDestroyController;
use App\Http\Controllers\Ventes\CommandeVenteIndexController;
use App\Http\Controllers\Ventes\CommandeVenteShowController;
use App\Http\Controllers\Ventes\CommandeVenteStoreController;
use App\Http\Controllers\Ventes\CommandeVenteUpdateController;
use App\Http\Controllers\Ventes\CommissionVenteIndexController;
use App\Http\Controllers\Ventes\CommissionVenteShowController;
use App\Http\Controllers\Ventes\EncaissementVenteIndexController;
use App\Http\Controllers\Ventes\EncaissementVenteStoreController;
use App\Http\Controllers\Ventes\FactureVenteAnnulerController;
use App\Http\Controllers\Ventes\FactureVenteIndexController;
use App\Http\Controllers\Ventes\FactureVenteShowController;
use App\Http\Controllers\Ventes\VersementCommissionStoreController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes Ventes
|--------------------------------------------------------------------------
| Une vente = une commande (lignes produits fabricables) liée à un véhicule.
| La facture est créée automatiquement à la création de la commande.
|
| Statuts facture   : impayee → partiel → payee | annulee
| Statuts commande  : active | annulee
|
| ⚠ RÈGLE MÉTIER : suppression physique interdite.
|   DELETE /{id} → annulation (rétrocompat). Préférer POST /{id}/annuler.
*/

// ── Création one-shot : véhicule + propriétaire + livreur ──────────────────
Route::post('/ventes/one-shot', VehiculeOneShotController::class)
    ->middleware('permission:vehicules.create');

// ── Commandes de vente ─────────────────────────────────────────────────────
Route::prefix('ventes/commandes')->group(function () {
    Route::get('/', CommandeVenteIndexController::class)
        ->middleware('permission:commandes.read');
    Route::post('/', CommandeVenteStoreController::class)
        ->middleware('permission:commandes.create');
    Route::get('/{id}', CommandeVenteShowController::class)
        ->where('id', '[0-9]+')
        ->middleware('permission:commandes.read');
    Route::put('/{id}', CommandeVenteUpdateController::class)
        ->where('id', '[0-9]+')
        ->middleware('permission:commandes.update');
    Route::post('/{id}/annuler', CommandeVenteAnnulerController::class)
        ->where('id', '[0-9]+')
        ->middleware('permission:commandes.delete');
    // Rétrocompatibilité : DELETE délègue vers annulation (pas de suppression physique)
    Route::delete('/{id}', CommandeVenteDestroyController::class)
        ->where('id', '[0-9]+')
        ->middleware('permission:commandes.delete');
});

// ── Factures de vente (lecture + annulation) ───────────────────────────────
Route::prefix('ventes/factures')->group(function () {
    Route::get('/', FactureVenteIndexController::class)
        ->middleware('permission:factures-livraisons.read');
    Route::get('/{id}', FactureVenteShowController::class)
        ->where('id', '[0-9]+')
        ->middleware('permission:factures-livraisons.read');
    Route::post('/{id}/annuler', FactureVenteAnnulerController::class)
        ->where('id', '[0-9]+')
        ->middleware('permission:factures-livraisons.update');
});

// ── Encaissements de vente ─────────────────────────────────────────────────
Route::prefix('ventes/encaissements')->group(function () {
    Route::get('/', EncaissementVenteIndexController::class)
        ->middleware('permission:encaissements.read');
    Route::post('/', EncaissementVenteStoreController::class)
        ->middleware('permission:encaissements.create');
});

// ── Commissions de vente ───────────────────────────────────────────────────
Route::prefix('ventes/commissions')->group(function () {
    Route::get('/', CommissionVenteIndexController::class)
        ->middleware('permission:commissions.read');
    Route::get('/{id}', CommissionVenteShowController::class)
        ->where('id', '[0-9]+')
        ->middleware('permission:commissions.read');
    Route::post('/{id}/versements/{type}', VersementCommissionStoreController::class)
        ->where('id', '[0-9]+')
        ->middleware('permission:commissions.verser');
});
