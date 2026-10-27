<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardStats;
use Filament\Widgets\ChartWidget;

/**
 * Produkte je Einsatzgebiet (PSAgA, Rettung, Baumpflege …).
 */
class ApplicationAreasChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Einsatzgebiete';

    protected static ?string $description = 'Produkte je Einsatzgebiet';

    protected static ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $areas = app(DashboardStats::class)->applicationAreas();

        return [
            'datasets' => [[
                'data' => array_values($areas),
                'backgroundColor' => ['#f59e0b', '#ef4444', '#22c55e', '#38bdf8', '#a855f7', '#ec4899', '#14b8a6', '#eab308', '#6366f1'],
                'borderWidth' => 0,
            ]],
            'labels' => array_keys($areas),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '55%',
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
