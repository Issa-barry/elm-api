<?php

namespace App\Providers;

use App\Models\Produit;
use App\Models\Stock;
use App\Observers\ProduitObserver;
use App\Observers\StockObserver;
use App\Services\UsineContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton par requête : le middleware ResolveUsineContext le peuple via X-Usine-Id
        $this->app->singleton(UsineContext::class);
    }

    public function boot(): void
    {
        Produit::observe(ProduitObserver::class);
        Stock::observe(StockObserver::class);
    }
}
