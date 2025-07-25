<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Wenn nicht eingeloggt: zum Filament-Login
    if (! auth()->check()) {
        return redirect()->route('filament.admin.auth.login');
    }

    // Wenn eingeloggt: direkt ins Dashboard
    return redirect()->route('filament.admin.pages.dashboard');
});
