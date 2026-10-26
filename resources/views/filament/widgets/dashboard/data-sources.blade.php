<x-filament-widgets::widget>
    @include('filament.widgets.dashboard._styles')

    <x-filament::section heading="Datenquellen" description="Woher die Daten kommen und wie aktuell sie sind">
        <div class="psa-sources">
            @foreach ($sources as $source)
                <div class="psa-source">
                    <span class="psa-dot {{ $source['status'] }}"></span>
                    <div>
                        <div style="font-weight: 700">{{ $source['name'] }}</div>
                        <div class="psa-small" style="margin-top: 0">{{ $source['detail'] }}</div>
                        <div class="psa-small">
                            @if ($source['status'] === 'manual')
                                manuell gepflegt
                            @elseif ($source['at'])
                                Stand: {{ $source['at'] }}
                            @else
                                noch kein Abruf
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
