<x-filament-widgets::widget>
    @include('filament.widgets.dashboard._styles')

    <x-filament::section heading="Datenqualität" description="Anteil aller Produkte, die bereits vollständig gepflegt sind">
        <div class="psa-rings">
            @foreach ($rows as $i => $row)
                <div>
                    <div class="psa-ring" style="--p: {{ $row['percent'] }}; --c: {{ $colors[$i % count($colors)] }}">
                        <b>{{ $row['percent'] }}&thinsp;%</b>
                    </div>
                    <div style="font-weight: 700">{{ $row['label'] }}</div>
                    <div class="psa-small">{{ number_format($row['count'], 0, ',', '.') }} von {{ number_format($row['total'], 0, ',', '.') }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
