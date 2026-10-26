<x-filament::page>
    <style>
        .psa-dash { display: grid; gap: 1.5rem; }
        .psa-row-3 { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); align-items: start; }
    </style>

    <div class="psa-dash">
        @livewire(\App\Filament\Widgets\WelcomeWidget::class)

        {{-- Kopfzeile: große Zahlen --}}
        @livewire(\App\Filament\Widgets\DashboardKpiWidget::class)

        {{-- Hersteller --}}
        @livewire(\App\Filament\Widgets\ManufacturerTilesWidget::class)
        @livewire(\App\Filament\Widgets\ManufacturerBarChartWidget::class)

        {{-- Datenqualität --}}
        @livewire(\App\Filament\Widgets\DataQualityWidget::class)

        <div class="psa-row-3">
            @livewire(\App\Filament\Widgets\ShopCoverageChartWidget::class)
            @livewire(\App\Filament\Widgets\ApplicationAreasChartWidget::class)
            @livewire(\App\Filament\Widgets\ProductGrowthChartWidget::class)
        </div>

        <div class="psa-row-3">
            @livewire(\App\Filament\Widgets\DataSourcesWidget::class)
            @livewire(\App\Filament\Widgets\AvailabilityWidget::class)
        </div>

        @livewire(\App\Filament\Widgets\RecentProductsGalleryWidget::class)
    </div>
</x-filament::page>
