<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        require __DIR__.'/api/auth.php';
    });

    // Routes backoffice (staff uniquement)
    Route::middleware(['auth:sanctum', 'user.type:staff', 'site.context'])->group(function () {
        require __DIR__.'/api/organisations.php';
        require __DIR__.'/api/dashboard.php';
        require __DIR__.'/api/roles.php';
        require __DIR__.'/api/produits.php';
        require __DIR__.'/api/users.php';
        require __DIR__.'/api/prestataires.php';
        require __DIR__.'/api/clients.php';
        require __DIR__.'/api/packings.php';
        require __DIR__.'/api/parametres.php';
        require __DIR__.'/api/notifications.php';
        require __DIR__.'/api/sites.php';
        require __DIR__.'/api/proprietaires.php';
        require __DIR__.'/api/livreurs.php';
        require __DIR__.'/api/vehicules.php';
        require __DIR__.'/api/livraisons.php';
        require __DIR__.'/api/ventes.php';
    });

    // Routes mobile (futur — client & prestataire)
    // Route::middleware(['auth:sanctum', 'user.type:client,prestataire'])->prefix('mobile')->group(function () {
    //     // require __DIR__.'/api/mobile.php';
    // });
});
