<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ImportRunEventsController;
use App\Http\Controllers\AliensImageController;
use App\Http\Controllers\PetzlDescriptionSyncRunEventsController;

/*
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('filament.admin.pages.dashboard')
        : redirect()->route('filament.admin.auth.login');
});
*/

Route::get('/', function () {
    return Auth::check() ? redirect('/admin') : redirect('/admin/login');
})->name('home');

if (app()->environment('local')) {
    Route::view('/dev/select-style-test', 'dev.select-style-test')->name('dev.select-style-test');
}

Route::get('/health', fn () => response()->json(['ok' => true, 'ts' => now()->toISOString()]));

Route::get('/whoami', function () {
    $u = auth()->user();
    return response()->json([
        'id'     => optional($u)->id,
        'email'  => optional($u)->email,
        'roles'  => $u ? $u->getRoleNames() : [],
        'cookie' => request()->cookie(config('session.cookie')) !== null,
        'guard'  => auth()->getDefaultDriver(),
    ]);
})->middleware('web');

Route::get('/imports/runs/{run}/events', [ImportRunEventsController::class, 'show'])
    ->middleware(['web', 'auth']);

Route::get('/aliens-image/{manufacturerId}/{productId}/{filename}', [AliensImageController::class, 'show'])
    ->where([
        'manufacturerId' => '[0-9]+',
        'productId' => '[0-9]+',
        'filename' => '.+',
    ]);

    Route::get(
        '/petzl-description-sync/runs/{run}/events',
        [PetzlDescriptionSyncRunEventsController::class, 'show']
    );