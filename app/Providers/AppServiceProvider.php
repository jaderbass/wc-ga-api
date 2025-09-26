<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Woo\WooRepositoryInterface;
use App\Services\Woo\WooApiRepository;
use App\Services\Woo\WooClient;
use App\Models\Shop;

/**
 * AppServiceProvider
 *
 * Registriert zentrale Container-Bindings für GeoAlpin/Woo:
 * - Bindet WooRepositoryInterface auf WooApiRepository, konstruiert mit einem
 *   "konfigurationsbasierten" Shop (liest base_url, api_version, key, secret aus config('woo.*')).
 *
 * Hinweise:
 * - Dieses Binding macht es möglich, WooParentResolver oder ProductExportOrchestrator
 *   via DI zu verwenden, ohne überall manuell Repo/Client zu bauen.
 * - Falls du mandanten-/shopbezogen arbeiten willst, kannst du später per
 *   Contextual Binding (oder Middleware, die einen aktuellen Shop in den Container setzt)
 *   gezielt einen anderen Shop durchreichen.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // WooRepositoryInterface → WooApiRepository (Default: Konfig-basierter Shop)
        $this->app->bind(WooRepositoryInterface::class, function ($app) {
            // Shop-Attr. aus Config auf ein flüchtiges Shop-Model mappen
            $shop = new Shop();
            $shop->base_url        = rtrim((string) config('woo.api.base_url'), '/');
            $shop->api_version     = (string) config('woo.default_api_version', 'wc/v3');
            $shop->consumer_key    = (string) config('woo.api.key');
            $shop->consumer_secret = (string) config('woo.api.secret');

            // Minimal-Validierung
            if (empty($shop->base_url) || empty($shop->consumer_key) || empty($shop->consumer_secret)) {
                throw new \RuntimeException('Woo API config incomplete: please set woo.api.base_url, woo.api.key, woo.api.secret.');
            }

            $client = new WooClient($shop);
            return new WooApiRepository($client);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
