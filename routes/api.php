  <?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        require __DIR__.'/api/auth.php';
    });

    require __DIR__.'/api/produits.php';
    require __DIR__.'/api/users.php';
    require __DIR__.'/api/prestataires.php';
    require __DIR__.'/api/clients.php';
});
