<?php

namespace App\Providers;

use App\Models\Shop;
use App\Models\User;
use App\Services\ProductNaming\DefaultProductNameBuilder;
use App\Services\ProductNaming\NameTemplateRegistry;
use App\Services\ProductNaming\ProductPropertyExtractor;
use App\Services\Woo\WooClient;
use App\Support\SystemLock;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

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
        /**
         * Prevent destructive Artisan commands from running in production.
         *
         * This is a safety net to avoid accidentally wiping the production database
         * (e.g. via `migrate:fresh`, `db:wipe`, etc.).
         *
         * Override (ONLY if you really know what you're doing):
         * - set ALLOW_DESTRUCTIVE_COMMANDS=true in the environment for a one-off run
         */
        if (app()->environment('production') && app()->runningInConsole()) {
            $command = $_SERVER['argv'][1] ?? null;

            $forbidden = [
                'migrate:fresh',
                'migrate:reset',
                'db:wipe',
                'schema:drop',
            ];

            $allow = filter_var(env('ALLOW_DESTRUCTIVE_COMMANDS', false), FILTER_VALIDATE_BOOL);

            if (! $allow && is_string($command) && in_array($command, $forbidden, true)) {
                fwrite(STDERR, PHP_EOL);
                fwrite(STDERR, "ABORTED: '{$command}' is blocked in production for safety.".PHP_EOL);
                fwrite(STDERR, 'If you really intend to run it, set ALLOW_DESTRUCTIVE_COMMANDS=true temporarily.'.PHP_EOL);
                fwrite(STDERR, PHP_EOL);
                exit(1);
            }
        }

        /**
         * Betriebsschalter (siehe App\Support\SystemLock): abgeschaltet laufen
         * nur Wartungsbefehle, Queue-Worker pausieren.
         */
        if (app()->runningInConsole() && basename((string) ($_SERVER['argv'][0] ?? '')) === 'artisan') {
            $command = $_SERVER['argv'][1] ?? null;

            if (SystemLock::isLocked() && ! SystemLock::allowsCommand($command)) {
                fwrite(STDERR, PHP_EOL."Datenbank ist abgeschaltet – '{$command}' wird nicht ausgeführt.".PHP_EOL.PHP_EOL);
                exit(1);
            }
        }

        Queue::looping(fn () => ! SystemLock::isLocked());

        Gate::before(function ($user, $ability = null, $arguments = []) {
            // Geschützte Konten: hier entscheidet immer die UserPolicy
            $target = $arguments[0] ?? null;
            if ($target instanceof User && $target->isProtectedAccount()) {
                return null;
            }

            // <<< DEINE Mailadresse hier eintragen >>>
            return $user && $user->email === 'joerg@jaderbass.de' ? true : null;
        });
    }
}
