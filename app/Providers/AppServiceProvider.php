<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     * This method is called after all other service providers have been registered.
     * It is a good place to register Filament navigation items and set the default dashboard.
     */
    public function boot(): void
    {
        //
    }
}
