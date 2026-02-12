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
    Route::get('/', FacturePackingIndexController::class);

    // Prévisualisation avant facturation
    Route::get('/preview', FacturePackingPreviewController::class);

    // Comptabilité - récapitulatif par prestataire
    Route::get('/comptabilite', FacturePackingComptabiliteController::class);

    // Détail d'une facture
    Route::get('/{id}', FacturePackingShowController::class)->where('id', '[0-9]+');

    // Créer une facture
    Route::post('/', FacturePackingStoreController::class);

    // Supprimer une facture
    Route::delete('/{id}', FacturePackingDestroyController::class)->where('id', '[0-9]+');

    // Versements (paiements partiels)
    Route::get('/{id}/versements', VersementIndexController::class)->where('id', '[0-9]+');
    Route::post('/{id}/versements', VersementStoreController::class)->where('id', '[0-9]+');
    Route::delete('/{factureId}/versements/{versementId}', VersementDestroyController::class)
        ->where(['factureId' => '[0-9]+', 'versementId' => '[0-9]+']);
});
