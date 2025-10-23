<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Shop;
use App\Services\Woo\WooClient;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            WooClient::class,
            function ($app, array $params = []) {
                // Falls ein Shop explizit übergeben wurde, nutze ihn:
                if (isset($params['shop']) && $params['shop'] instanceof Shop) {
                    return new WooClient($params['shop']);
                }
                // Sonst Default-Shop laden:
                $shop = Shop::query()->where('is_default', true)->firstOrFail();
                return new WooClient($shop);
            }
        );
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
