<x-filament-widgets::widget>
    @include('filament.widgets.dashboard._styles')

    <x-filament::section heading="Unsere Hersteller" description="Produkte, Varianten und Bildabdeckung je Hersteller – Klick öffnet die Produktliste">
        <div class="psa-grid">
            @foreach ($rows as $row)
                <a class="psa-tile" href="{{ $productsUrl }}?tableFilters[manufacturer_id][value]={{ $row['id'] }}">
                    <div class="psa-logo">
                        @if ($row['logo'])
                            <img src="{{ $row['logo'] }}" alt="{{ $row['name'] }}" loading="lazy">
                        @else
                            <span class="psa-logo-text">{{ $row['name'] }}</span>
                        @endif
                    </div>
                    <div class="psa-nums">
                        <div>
                            <div class="psa-num">{{ number_format($row['products'], 0, ',', '.') }}</div>
                            <div class="psa-cap">Produkte</div>
                        </div>
                        <div>
                            <div class="psa-num">{{ number_format($row['variations'], 0, ',', '.') }}</div>
                            <div class="psa-cap">Varianten</div>
                        </div>
                    </div>
                    <div class="psa-bar"><span style="width: {{ $row['image_share'] }}%"></span></div>
                    <div class="psa-small">{{ $row['image_share'] }} % mit Bild</div>
                </a>
            @endforeach
        </div>

        @if ($hidden > 0)
            <button type="button" class="psa-more" wire:click="toggle">
                {{ $showAll ? 'Weniger anzeigen' : "Alle Hersteller anzeigen (+{$hidden})" }}
            </button>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
