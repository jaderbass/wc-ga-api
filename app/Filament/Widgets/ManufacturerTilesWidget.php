<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Services\Dashboard\DashboardStats;
use Filament\Widgets\Widget;

/**
 * Hersteller-Kacheln mit Logo, Produkten, Varianten und Bildanteil.
 */
class ManufacturerTilesWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.manufacturer-tiles';

    protected int|string|array $columnSpan = 'full';

    public bool $showAll = false;

    public const VISIBLE = 12;

    protected function getViewData(): array
    {
        $rows = app(DashboardStats::class)->manufacturers();

        return [
            'rows' => $this->showAll ? $rows : array_slice($rows, 0, self::VISIBLE),
            'hidden' => max(0, count($rows) - self::VISIBLE),
            'productsUrl' => ProductResource::getUrl('index'),
        ];
    }

    public function toggle(): void
    {
        $this->showAll = ! $this->showAll;
    }
}
