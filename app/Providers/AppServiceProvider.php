<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Shop;
use App\Services\Woo\WooClient;
use Illuminate\Support\Facades\Gate;
use App\Services\ProductNaming\DefaultProductNameBuilder;
use App\Services\ProductNaming\NameTemplateRegistry;
use App\Services\ProductNaming\ProductPropertyExtractor;

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

        // Registry is simple/config-based -> singleton is fine.
        $this->app->singleton(NameTemplateRegistry::class);

        // Builder is stateless -> singleton is fine.
        $this->app->singleton(DefaultProductNameBuilder::class);

        $this->app->singleton(ProductPropertyExtractor::class);
    }


    /**
     * Bootstrap any application services.
     * This method is called after all other service providers have been registered.
     * It is a good place to register Filament navigation items and set the default dashboard.
     */

    public function boot(): void
    {
        // ... (deine evtl. bestehenden Einträge)

        Gate::before(function ($user, $ability = null) {
            // <<< DEINE Mailadresse hier eintragen >>>
            return $user && $user->email === 'joerg@jaderbass.de' ? true : null;
        });
    }

}
