<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardStats;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Shop und Datenbank: im Shop / nur Datenbank / ausgelaufen, aber noch im Umlauf.
 */
class ShopCoverageChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Shop & Datenbank';

    protected static ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    public function getDescription(): string|Htmlable|null
    {
        $at = app(DashboardStats::class)->shopCoverage()['snapshot_at'];

        return 'Ausgelaufene Artikel bleiben für Prüfung & Inventarisierung erhalten'.($at ? " · Shop-Stand {$at}" : '');
    }

    protected function getData(): array
    {
        $c = app(DashboardStats::class)->shopCoverage();

        return [
            'datasets' => [[
                'data' => [$c['shop'], $c['database'], $c['discontinued']],
                'backgroundColor' => ['#22c55e', '#38bdf8', '#a855f7'],
                'borderWidth' => 0,
            ]],
            'labels' => ['Im Shop', 'Nur Datenbank', 'Ausgelaufen (im Umlauf)'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '65%',
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
