<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
  /**
   * Das Policy-Array kann leer bleiben, falls ihr keine Policies nutzt.
   *
   * @var array<class-string, class-string>
   */
  protected $policies = [
    // \App\Models\User::class => \App\Policies\UserPolicy::class,
  ];

  /**
   * Bootstrap any authentication / authorization services.
   */
  public function boot(): void
  {
    $this->registerPolicies();
    // Weitere Gates/Policies bei Bedarf hier registrieren.
  }
}
