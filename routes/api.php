  <?php

use App\Http\Controllers\Payment\Stripe\PaymentIntentStoreController;
use App\Http\Controllers\Payment\Stripe\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // require __DIR__.'/api/auth.php';
});

 