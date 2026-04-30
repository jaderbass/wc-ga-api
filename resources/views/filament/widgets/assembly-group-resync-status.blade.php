<x-filament-widgets::widget>
    <x-filament::section>
        <div wire:poll.2s>
            @php
                $run = $this->getRun();
            @endphp
            @if ($run)
                <div class="space-y-1 text-sm">
                    <div><strong>Status:</strong> {{ $run->status }}</div>
                    <div><strong>Fortschritt:</strong> {{ $run->processed }} / {{ $run->total }}</div>
                    <div><strong>Aktualisiert:</strong> {{ $run->updated }}</div>
                </div>
            @else
                <div class="text-sm text-gray-500">
                    Es wurde noch keine Baugruppen-Neuberechnung gestartet.
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
