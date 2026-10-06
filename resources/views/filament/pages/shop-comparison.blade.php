<x-filament-panels::page>
    @php
        $snapshot = $this->getSnapshot();
        $stats = $this->getStats();
        $cards = $stats ? [
            ['Shop-Einträge', $stats['units_total'], 'einfache Produkte und Varianten'],
            ['Gefunden (SKU)', $stats['units']['sku'] ?? 0, 'eindeutig über die Artikelnummer'],
            ['Gefunden (nur EAN)', $stats['units']['ean'] ?? 0, 'SKU passt nicht, EAN schon'],
            ['Mehrdeutig', $stats['units']['ambiguous'] ?? 0, 'mehrere Treffer in der Datenbank'],
            ['Nur im Shop', $stats['units']['shop_only'] ?? 0, 'nicht in der Datenbank gefunden'],
            ['Ohne SKU/EAN', $stats['units']['no_key'] ?? 0, 'kein Schlüssel zum Vergleichen'],
            ['Doppelte SKUs im Shop', $stats['duplicate_skus'], 'gleiche SKU mehrfach im Shop'],
            ['Nur in der Datenbank', $stats['db_products_only'], 'von '.$stats['db_products'].' Produkten'],
        ] : [];
    @endphp

    @if (! $stats)
        <x-filament::section>
            Noch keine Shop-Momentaufnahme vorhanden. Auf dem Server ausführen:
            <code>php artisan woo:snapshot</code> (liest den Shop nur, schreibt nichts hinein).
        </x-filament::section>
    @else
        <div style="font-size: 0.875rem; opacity: 0.75;">
            Momentaufnahme #{{ $snapshot->id }} vom {{ $snapshot->finished_at?->format('d.m.Y H:i') }} –
            {{ $snapshot->products_count }} Produkte, {{ $snapshot->variations_count }} Varianten aus
            {{ $snapshot->shop?->base_url }}. Neu einlesen: <code>php artisan woo:snapshot</code>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            @foreach ($cards as [$title, $value, $hint])
                <x-filament::section>
                    <div style="font-size: 0.8rem; opacity: 0.75;">{{ $title }}</div>
                    <div style="font-size: 1.75rem; font-weight: 600; line-height: 1.2;">{{ number_format($value, 0, ',', '.') }}</div>
                    <div style="font-size: 0.75rem; opacity: 0.6;">{{ $hint }}</div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
