<?php

namespace App\Providers\Filament;

use App\Livewire\CategoryResyncStatusWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite; // <— wichtig
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
    /**
 * Registriert und konfiguriert das Filament-Admin-Panel.
 *
 * - Lässt Filament sein Standard-Theme laden (kein ->viteTheme()).
 * - Bindet projektweite Admin-CSS-Overrides (gebaut via Vite) per Render-Hook ein,
 *   damit Light/Dark-Mode sicher und in richtiger Reihenfolge funktionieren.
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * Baut das Panel-Objekt mit allen gewünschten Optionen/Middleware/Widgets.
     *
     * Wichtige Punkte:
     * - Kein Ersetzen des Filament-Themes (dadurch bleiben Core-Styles intakt).
     * - Branding/Colors/Widgets/Middleware wie in deiner bestehenden Konfiguration.
     *
     * @param  \Filament\Panel  $panel  Das zu konfigurierende Panel-Objekt.
     * @return \Filament\Panel         Das konfigurierte Panel.
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->registration()
            ->profile()
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            /* ->pages([
                Pages\Dashboard::class,
            ]) */
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->databaseNotifications()
            ->authMiddleware([
                Authenticate::class,
            ])
            ->favicon(asset('favicon.svg'))
            // ->brandLogo(asset('images/geoalpin-shop-favicon.svg'))
            ->brandLogo(fn () => view('components.filament-brand-logo'))
            ->brandLogoHeight('2rem')
            ->brandName('WooCommerce-Geoalpin-API')
            ->authGuard('web');
            // WICHTIG: kein ->viteTheme() und kein ->vite() hier – Theme bleibt unverändert,
            // CSS-Overrides werden unten in boot() via Render-Hook eingebunden.
    }

    /**
     * Bootstrapping für das Admin-Panel.
     *
     * Bindet die Vite-gebaute CSS-Datei `resources/css/filament/admin-overrides.css`
     * über einen Render-Hook als <link>-Tag am Ende des <head> ein.
     *
     * Vorteile:
     * - Lädt NACH dem Filament-Theme (korrekte Cascade/Reihenfolge).
     * - Kein Überschreiben des Core-Themes.
     * - Unabhängig von Tailwind-/Vite-Importbesonderheiten.
     *
     * Hinweis:
     * - Die Datei muss in `vite.config.js` als Input registriert sein.
     * - Light/Dark-Schalter erfolgt in Filament v3.2 über `.dark` auf <html>.
     *
     * @return void
     */
    public function boot(): void
    {
        Filament::serving(function () {
            Filament::registerRenderHook('panels::head.end', function (): string {
                $href = Vite::asset('resources/css/filament/admin-overrides.css');

                return '<link rel="stylesheet" href="' . $href . '">';
            });

            Filament::registerRenderHook('panels::topbar.end', function (): string {
                return view('filament.components.category-resync-status')->render();
            });
        });
    }
}
