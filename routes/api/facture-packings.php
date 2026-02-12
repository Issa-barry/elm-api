<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FacturePacking\FacturePackingIndexController;
use App\Http\Controllers\FacturePacking\FacturePackingShowController;
use App\Http\Controllers\FacturePacking\FacturePackingPreviewController;
use App\Http\Controllers\FacturePacking\FacturePackingStoreController;
use App\Http\Controllers\FacturePacking\FacturePackingDestroyController;
use App\Http\Controllers\FacturePacking\FacturePackingComptabiliteController;
use App\Http\Controllers\FacturePacking\VersementIndexController;
use App\Http\Controllers\FacturePacking\VersementStoreController;
use App\Http\Controllers\FacturePacking\VersementDestroyController;

/*
|--------------------------------------------------------------------------
| Routes API Factures Packings
|--------------------------------------------------------------------------
|
| Gestion des factures de packings pour les prestataires
|
*/

Route::prefix('facture-packings')->group(function () {
    // Liste des factures
    Route::get('/', FacturePackingIndexController::class)->middleware('permission:facture-packings.index');

    // Prévisualisation avant facturation
    Route::get('/preview', FacturePackingPreviewController::class)->middleware('permission:facture-packings.preview');

    // Comptabilité - récapitulatif par prestataire
    Route::get('/comptabilite', FacturePackingComptabiliteController::class)->middleware('permission:facture-packings.comptabilite');

    // Détail d'une facture
    Route::get('/{id}', FacturePackingShowController::class)->where('id', '[0-9]+')->middleware('permission:facture-packings.show');

    // Créer une facture
    Route::post('/', FacturePackingStoreController::class)->middleware('permission:facture-packings.store');

    // Supprimer une facture
    Route::delete('/{id}', FacturePackingDestroyController::class)->where('id', '[0-9]+')->middleware('permission:facture-packings.destroy');

    // Versements (paiements partiels)
    Route::get('/{id}/versements', VersementIndexController::class)->where('id', '[0-9]+')->middleware('permission:versements.index');
    Route::post('/{id}/versements', VersementStoreController::class)->where('id', '[0-9]+')->middleware('permission:versements.store');
    Route::delete('/{factureId}/versements/{versementId}', VersementDestroyController::class)
        ->where(['factureId' => '[0-9]+', 'versementId' => '[0-9]+'])->middleware('permission:versements.destroy');
});
