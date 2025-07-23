<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Dashboard extends Page
{
    protected static ?string $slug = 'dashboard';       // gleiche Route
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $title = 'Admin-Dashboard';
    protected static string $view = 'filament.pages.dashboard';
}
