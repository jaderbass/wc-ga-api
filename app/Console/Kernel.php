<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
  /**
   * Hier registrierst du deine Artisan-Commands.
   *
   * @var array<int, class-string>
   */
  protected $commands = [
    \App\Console\Commands\FixPermissionGuards::class,
    \App\Console\Commands\PermissionHealthCheck::class,
    \App\Console\Commands\PermissionFixPlan::class,
    \App\Console\Commands\WooSyncVariationsCommand::class,
    \App\Console\Commands\WooPingCommand::class,
    \App\Console\Commands\WooResetMappingsCommand::class,
    \App\Console\Commands\MakeImportMapping::class,
  ];

  /**
   * Geplante Tasks (optional).
   */
  protected function schedule(Schedule $schedule): void
  {
    // Beispiel (optional):
    // $schedule->command('permissions:health --export-stats=perm_stats.csv')->dailyAt('03:00');
  }

  /**
   * Registriert die Konsolenbefehle und lädt routes/console.php.
   */
  protected function commands(): void
  {
    $this->load(__DIR__ . '/Commands');

    require base_path('routes/console.php');
  }
}
