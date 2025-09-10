<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
  /**
   * Die Ausnahmen, die nicht gemeldet werden sollen.
   * @var array<int, class-string<Throwable>>
   */
  protected $dontReport = [
    //
  ];

  /**
   * Sensible Eingabefelder, die nie geflasht werden sollen.
   * @var array<int, string>
   */
  protected $dontFlash = [
    'current_password',
    'password',
    'password_confirmation',
  ];

  public function register(): void
  {
    // Jede nicht abgefangene Exception kurz und knackig loggen
    $this->reportable(function (Throwable $e) {
      Log::error('UNHANDLED_EXCEPTION', [
        'msg'  => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
      ]);
    });
  }
}
