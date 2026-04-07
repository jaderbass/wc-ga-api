<div wire:poll.5s class="ms-4">
    @if ($this->run && $this->text)
        <div class="flex items-center gap-2 text-sm">
            @if ($this->status === 'queued')
                <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    <x-heroicon-o-clock class="h-4 w-4" />
                    <span>{{ $this->text }}</span>
                </span>
            @elseif ($this->status === 'running')
                <span class="inline-flex items-center gap-1 rounded-md bg-primary-50 px-2 py-1 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                    <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" />
                    <span>{{ $this->text }}</span>
                    @if ($this->progress !== null)
                        <span class="font-medium">· {{ $this->progress }} %</span>
                    @endif
                </span>
            @endif
        </div>
    @endif
</div>
