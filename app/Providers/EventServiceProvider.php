<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
  /**
   * Event-Listener Mappings (optional).
   *
   * @var array<class-string, array<int, class-string>>
   */
  protected $listen = [
    // \App\Events\SomethingHappened::class => [
    //     \App\Listeners\DoSomething::class,
    // ],
  ];

  /**
   * Register any events for your application.
   */
  public function boot(): void
  {
    //
  }

  /**
   * Bestimmt, ob Events automatisch entdeckt werden sollen (optional).
   */
  public function shouldDiscoverEvents(): bool
  {
    return false;
  }
}
