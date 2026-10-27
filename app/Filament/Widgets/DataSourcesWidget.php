<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardStats;
use Filament\Widgets\Widget;

/**
 * Datenquellen mit Status: automatisch aktuell / überfällig / manuell gepflegt.
 */
class DataSourcesWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.data-sources';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return ['sources' => app(DashboardStats::class)->sources()];
    }
}
