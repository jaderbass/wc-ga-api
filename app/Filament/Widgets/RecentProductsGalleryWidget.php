<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Services\Dashboard\DashboardStats;
use Filament\Widgets\Widget;

/**
 * Zuletzt hinzugefügte Produkte als Bildleiste.
 */
class RecentProductsGalleryWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.recent-gallery';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'products' => app(DashboardStats::class)->recentWithImages(12),
            'editUrl' => fn (int $id) => ProductResource::getUrl('edit', ['record' => $id]),
        ];
    }
}
