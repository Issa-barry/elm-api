  <?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        require __DIR__.'/api/auth.php';
    });

    // Routes protégées par authentification
    Route::middleware('auth:sanctum')->group(function () {
        require __DIR__.'/api/roles.php';
        require __DIR__.'/api/produits.php';
        require __DIR__.'/api/users.php';
        require __DIR__.'/api/prestataires.php';
        require __DIR__.'/api/clients.php';
        require __DIR__.'/api/packings.php';
        require __DIR__.'/api/facture-packings.php';
        require __DIR__.'/api/parametres.php';
    });
});
