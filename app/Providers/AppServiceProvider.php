<?php

namespace App\Providers;

use App\Models\Produit;
use App\Models\Stock;
use App\Observers\ProduitObserver;
use App\Observers\StockObserver;
use App\Services\SiteContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton par requête : le middleware ResolveSiteContext le peuple via X-Site-Id
        $this->app->singleton(SiteContext::class);
    }

    public function boot(): void
    {
        // Le super_admin contourne tous les checks de permission Spatie/Gate
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
        });

        Produit::observe(ProduitObserver::class);
        Stock::observe(StockObserver::class);
    }
}
