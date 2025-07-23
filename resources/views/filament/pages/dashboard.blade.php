<x-filament::page>
    <div class="space-y-6">
        {{-- Welcome --}}
        <div class="grid grid-cols-1">
            @livewire(\App\Filament\Widgets\WelcomeWidget::class)
        </div>

        {{-- Zähler nebeneinander --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @livewire(\App\Filament\Widgets\ProductCountWidget::class)
            @livewire(\App\Filament\Widgets\ManufacturerCountWidget::class)
        </div>

        {{-- Tabelle --}}
        <div class="grid grid-cols-1">
            @livewire(\App\Filament\Widgets\RecentProductsWidget::class)
        </div>
    </div>
</x-filament::page>
