<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Woo\IdentifierStrategy;
use App\Services\Woo\WooLinkStore;

class WooServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IdentifierStrategy::class, fn() => new IdentifierStrategy());
        $this->app->singleton(WooLinkStore::class, fn() => new WooLinkStore());
    }

    public function boot(): void
    {
        //
    }
}
