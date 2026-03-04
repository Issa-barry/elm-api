<?php

use App\Http\Controllers\Site\SiteChangeDefaultController;
use App\Http\Controllers\Site\SiteDestroyController;
use App\Http\Controllers\Site\SiteIndexController;
use App\Http\Controllers\Site\SitePatchController;
use App\Http\Controllers\Site\SiteShowController;
use App\Http\Controllers\Site\SiteStoreController;
use App\Http\Controllers\Site\SiteUserAffectController;
use App\Http\Controllers\Site\SiteUsersIndexController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes API Sites
|--------------------------------------------------------------------------
|
| Gestion du parc de sites et des affectations user ↔ site.
| La création/modification/suppression est réservée aux utilisateurs siège.
|
*/

Route::prefix('sites')->group(function () {
    // Lecture — accessible à tous les staff authentifiés
    Route::get('/', SiteIndexController::class)->middleware('permission:sites.read');
    Route::get('/{id}', SiteShowController::class)->where('id', '[0-9]+')->middleware('permission:sites.read');

    // Création — super_admin uniquement
    Route::post('/', SiteStoreController::class)->middleware('role:super_admin');

    // Mise à jour partielle — siège uniquement (vérification dans le controller)
    Route::patch('/{id}', SitePatchController::class)->where('id', '[0-9]+')->middleware('permission:sites.update');

    // Suppression (soft delete) — super_admin uniquement
    Route::delete('/{id}', SiteDestroyController::class)->where('id', '[0-9]+')->middleware('role:super_admin');

    // Changer le site par défaut de l'utilisateur connecté
    Route::patch('/{id}/set-default', SiteChangeDefaultController::class)->where('id', '[0-9]+');

    // Utilisateurs affectés à un site
    Route::get('/{siteId}/users', SiteUsersIndexController::class)
        ->where('siteId', '[0-9]+')
        ->middleware('permission:sites.read');

    // Affectation user ↔ site — siège uniquement
    Route::post('/{siteId}/users', [SiteUserAffectController::class, 'attach'])
        ->where('siteId', '[0-9]+')
        ->middleware('permission:sites.update');

    Route::delete('/{siteId}/users/{userId}', [SiteUserAffectController::class, 'detach'])
        ->where(['siteId' => '[0-9]+', 'userId' => '[0-9]+'])
        ->middleware('permission:sites.update');
});
