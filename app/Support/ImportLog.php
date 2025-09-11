<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

final class ImportLog
{
  public static function debug(string $msg, array $ctx = []): void
  {
    if (config('import.debug')) {
      // ImportLog::debug($msg, $ctx);

      // Hebe auf INFO an, damit es garantiert ins Log kommt
      Log::info($msg, $ctx);
      return;
    }

    // Normalfall: echtes Debug
    Log::debug($msg, $ctx);
  }

  public static function info(string $msg, array $ctx = []): void
  {
    Log::info($msg, $ctx);
  }

  public static function error(string $msg, array $ctx = []): void
  {
    Log::error($msg, $ctx);
  }
}
