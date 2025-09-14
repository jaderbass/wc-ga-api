<?php

use App\Http\Controllers\WooWebhookController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PingController;

/* Route::post('/webhooks/woo/{shop}', WooWebhookController::class)
->name('webhooks.woo.receive');

// Route::post('/webhooks/woo/ping', fn() => response()->json(['ok' => true]))
//   ->name('webhooks.woo.ping');


Route::get('/webhooks/woo/ping', fn() => response()->json(['ok' => true]));
Route::post('/webhooks/woo/ping', fn() => response()->json(['ok' => true]));

// Test-Ping (Reachability)
Route::match(['GET', 'POST'], '/webhooks/woo/ping', fn() => response()->json(['ok' => true]));

// Echte Webhook-Route (Woo → dein Backend)
Route::post('/webhooks/woo/{shop}', WooWebhookController::class)
  ->name('webhooks.woo.receive'); */