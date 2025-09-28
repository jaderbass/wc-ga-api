<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
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
   * Hinweis:
   * - Ab neueren Laravel-Versionen ist kein eigener RouteServiceProvider zwingend nötig.
   * - Dieser Stub genügt, damit der Eintrag in config/app.php auflösbar ist.
   */
  public function boot(): void
  {
    //
  }
}
