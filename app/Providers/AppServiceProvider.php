<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
     * 
     * Ausführung mit:
     * php artisan make:product-migration oder
     * php artisan make:product-migration custom_products_table
     */
    public function boot(): void
    {
        //
    }
}
