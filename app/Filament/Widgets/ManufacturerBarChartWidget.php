<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardStats;
use Filament\Widgets\ChartWidget;

/**
 * Hersteller im Vergleich: Produkte und Varianten (Top 15, Rest als "Weitere").
 */
class ManufacturerBarChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Hersteller im Vergleich';

    protected static ?string $description = 'Produkte und Varianten je Hersteller';

    protected static ?string $maxHeight = '520px';

    protected int|string|array $columnSpan = 'full';

    public const TOP = 15;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = collect(app(DashboardStats::class)->manufacturers());
        $top = $rows->take(self::TOP);
        $rest = $rows->slice(self::TOP);

        $labels = $top->pluck('name')->all();
        $products = $top->pluck('products')->all();
        $variations = $top->pluck('variations')->all();

        if ($rest->isNotEmpty()) {
            $labels[] = 'Weitere ('.$rest->count().')';
            $products[] = $rest->sum('products');
            $variations[] = $rest->sum('variations');
        }

        return [
            'datasets' => [
                ['label' => 'Produkte', 'data' => $products, 'backgroundColor' => '#f59e0b', 'borderRadius' => 4],
                ['label' => 'Varianten', 'data' => $variations, 'backgroundColor' => '#38bdf8', 'borderRadius' => 4],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['position' => 'top']],
            'scales' => ['x' => ['beginAtZero' => true]],
        ];
    }
}
