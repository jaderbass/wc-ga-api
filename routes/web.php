<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WooWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Csrf;

/* Route::get('/', function () {
    // Wenn nicht eingeloggt: zum Filament-Login
    if (! auth()->check()) {
        return redirect()->route('filament.admin.auth.login');
    }

    // Wenn eingeloggt: direkt ins Dashboard
    return redirect()->route('filament.admin.pages.dashboard');
}); */

Route::match(['GET', 'POST'], '/webhooks/woo/ping', fn() => response()->json(['ok' => true]))
    ->withoutMiddleware([Csrf::class]);
Route::post('/webhooks/woo/{shop}', \App\Http\Controllers\WooWebhookController::class)
    ->name('webhooks.woo.receive')
    ->withoutMiddleware([Csrf::class]);;
