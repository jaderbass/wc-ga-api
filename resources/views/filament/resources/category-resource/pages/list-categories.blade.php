<x-filament-panels::page>
    @php
        $run = $this->latestResyncRun;
        $text = $this->resyncStatusText;
    @endphp

    @if($run && $text)
        <div class="mb-4">
            @php
                $classes = match ($run->status) {
                    'queued' => 'border-gray-200 bg-gray-50 text-gray-800',
                    'running' => 'border-yellow-200 bg-yellow-50 text-yellow-800',
                    'finished' => 'border-green-200 bg-green-50 text-green-800',
                    'failed' => 'border-red-200 bg-red-50 text-red-800',
                    default => 'border-gray-200 bg-gray-50 text-gray-800',
                };
            @endphp

            <div class="rounded-lg border px-4 py-3 text-sm {{ $classes }}">
                <div class="font-medium">
                    Kategorien-Neuzuordnung
                </div>

                <div class="mt-1">
                    {{ $text }}
                </div>

                @if($run->finished_at)
                    <div class="mt-1 text-xs opacity-80">
                        Beendet: {{ $run->finished_at->format('d.m.Y H:i:s') }}
                    </div>
                @elseif($run->started_at)
                    <div class="mt-1 text-xs opacity-80">
                        Gestartet: {{ $run->started_at->format('d.m.Y H:i:s') }}
                    </div>
                @endif

                @if($run->status === 'failed' && filled($run->message))
                    <div class="mt-2 text-xs">
                        {{ $run->message }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
