<?php

use App\Http\Controllers\WooWebhookController;
use Illuminate\Support\Facades\Route;

// Test-Ping (Reachability)
Route::match(['GET', 'POST'], '/webhooks/woo/ping', fn() => response()->json(['ok' => true]));

// Echte Webhook-Route (Woo → dein Backend)
Route::post('/webhooks/woo/{shop}', WooWebhookController::class)
  ->name('webhooks.woo.receive');