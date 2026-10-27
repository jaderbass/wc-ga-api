<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardStats;
use Filament\Widgets\Widget;

/**
 * Datenqualität: Anteil der Produkte mit Titelbild, Beschreibung, Kategorie, EK, Einsatzgebiet.
 */
class DataQualityWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.data-quality';

    protected int|string|array $columnSpan = 'full';

    public const COLORS = ['#f59e0b', '#22c55e', '#38bdf8', '#a855f7', '#ec4899'];

    protected function getViewData(): array
    {
        return ['rows' => app(DashboardStats::class)->quality(), 'colors' => self::COLORS];
    }
}
