<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('filament.admin.pages.dashboard')
        : redirect()->route('filament.admin.auth.login');
});

if (app()->environment('local')) {
    Route::view('/dev/select-style-test', 'dev.select-style-test')->name('dev.select-style-test');
}
