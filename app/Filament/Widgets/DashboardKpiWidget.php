<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Kopfzeile: große Zahlen mit Verlauf der letzten 30 Tage.
 */
class DashboardKpiWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 5;
    }

    protected function getStats(): array
    {
        $t = app(DashboardStats::class)->totals();
        $n = fn (int $v): string => number_format($v, 0, ',', '.');

        $stat = fn (string $label, array $data, string $icon, string $color) => Stat::make($label, $n($data['value']))
            ->icon($icon)
            ->color($color)
            ->chart($data['trend'] ?: null)
            ->description($data['added'] > 0 ? '+'.$n($data['added']).' in 30 Tagen' : ' ')
            ->descriptionIcon($data['added'] > 0 ? 'heroicon-m-arrow-trending-up' : null)
            ->descriptionColor('success');

        return [
            $stat('Produkte', $t['products'], 'heroicon-o-cube', 'primary'),
            $stat('Varianten', $t['variations'], 'heroicon-o-squares-2x2', 'info'),
            $stat('Produktbilder', $t['images'], 'heroicon-o-photo', 'success'),
            Stat::make('Hersteller', $n($t['manufacturers']['value']))->icon('heroicon-o-building-office-2')->description('mit Produkten in der Datenbank'),
            Stat::make('Produktkategorien', $n($t['categories']['value']))->icon('heroicon-o-tag')->description('im Kategoriebaum'),
        ];
    }
}
