<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WooPingCommand
 *
 * Kleiner Sanity-Check für die WooCommerce API-Verbindung.
 * Führt einen GET auf /products?per_page=1 aus und gibt den Status
 * und die ersten Daten direkt im Artisan-Output aus.
 *
 * Usage:
 *   php artisan woo:ping
 *
 * Registration:
 *   - In app/Console/Kernel.php unter $commands[] eintragen:
 *       \App\Console\Commands\WooPingCommand::class,
 *
 * Hinweise:
 * - Nutzt config('woo.api.*') und config('woo.default_api_version').
 * - Ideal als erster Test nach .env-Setup (Keys/Base URL).
 * - Loggt die Antwort zusätzlich nach laravel.log.
 *
 * @author  JAderBass
 * @since   2025-09-19
 */
class WooPingCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'woo:ping';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Test WooCommerce API connectivity and credentials.';

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle(): int
  {
    $base   = rtrim((string) config('woo.api.base_url'), '/');
    $ver    = (string) config('woo.default_api_version', 'wc/v3');
    $key    = (string) config('woo.api.key');
    $secret = (string) config('woo.api.secret');

    if (empty($base) || empty($key) || empty($secret)) {
      $this->error('Missing Woo API config (base_url, key, secret). Check your .env / config/woo.php');
      return self::INVALID;
    }

    $url = $base . '/wp-json/' . $ver . '/products';

    $this->line("Pinging Woo API: {$url}?per_page=1");

    try {
      $resp = Http::withBasicAuth($key, $secret)
        ->acceptJson()
        ->get($url, ['per_page' => 1]);

      $status = $resp->status();
      $this->info("HTTP Status: {$status}");

      if ($resp->successful()) {
        $data = $resp->json();
        $count = is_array($data) ? count($data) : 0;
        $this->info("Received {$count} product(s). Showing first entry:");
        $this->line(json_encode($count > 0 ? $data[0] : [], JSON_PRETTY_PRINT));
        Log::info('woo:ping response', ['status' => $status, 'sample' => $count > 0 ? $data[0] : []]);
      } else {
        $this->error('Request failed. Body: ' . $resp->body());
        Log::error('woo:ping failed', ['status' => $status, 'body' => $resp->body()]);
      }

      return $resp->successful() ? self::SUCCESS : self::FAILURE;
    } catch (\Throwable $e) {
      $this->error('Exception: ' . $e->getMessage());
      Log::error('woo:ping exception', ['error' => $e->getMessage()]);
      return self::FAILURE;
    }
  }
}
