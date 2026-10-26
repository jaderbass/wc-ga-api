<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardStats;
use Filament\Widgets\Widget;

/**
 * Lagerampel: online verkaufbar / offline; Lagerstand aus dem Petzl-Händlerportal.
 */
class AvailabilityWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.availability';

    protected function getViewData(): array
    {
        return ['a' => app(DashboardStats::class)->availability()];
    }
}
