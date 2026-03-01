<?php

use App\Http\Controllers\Livraisons\VehiculeOneShotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes Livraisons
|--------------------------------------------------------------------------
| Création rapide : véhicule + propriétaire + livreur en une seule requête.
| Le reste du workflow (commandes, factures, encaissements) est dans ventes.php
*/

Route::post('/livraisons/one-shot', VehiculeOneShotController::class)
    ->middleware('permission:vehicules.create');
