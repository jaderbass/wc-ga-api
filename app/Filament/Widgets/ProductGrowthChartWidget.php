<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardStats;
use Filament\Widgets\ChartWidget;

/**
 * Wachstum: Produkte in der Datenbank am Monatsende (12 Monate).
 */
class ProductGrowthChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Wachstum';

    protected static ?string $description = 'Produkte in der Datenbank, letzte 12 Monate';

    protected static ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $growth = app(DashboardStats::class)->growth();

        return [
            'datasets' => [[
                'label' => 'Produkte',
                'data' => $growth['values'],
                'borderColor' => '#f59e0b',
                'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                'fill' => true,
                'tension' => 0.35,
                'pointRadius' => 3,
            ]],
            'labels' => $growth['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
