<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
  /**
   * Pfade, die von der CSRF-Prüfung ausgenommen sind.
   *
   * @var array<int, string>
   */
  protected $except = [
    'webhooks/woo/*', // <-- unser Webhook
  ];
}
