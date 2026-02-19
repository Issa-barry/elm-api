<?php

namespace App\Providers;

use App\Models\Produit;
use App\Observers\ProduitObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Produit::observe(ProduitObserver::class);
    }
}
