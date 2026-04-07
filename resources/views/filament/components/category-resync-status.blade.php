@php
    use App\Services\Categories\CategoryResyncStatus;

    $run = CategoryResyncStatus::latest();
    $status = CategoryResyncStatus::status();
    $text = CategoryResyncStatus::text();
    $progress = CategoryResyncStatus::progress();
@endphp

<div wire:poll.5s class="ms-4">
    @if ($run && $text)
        <div class="flex items-center gap-2 text-sm">
            @if ($status === 'queued')
                <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    <x-heroicon-o-clock class="h-4 w-4" />
                    <span>{{ $text }}</span>
                </span>
            @elseif ($status === 'running')
                <span class="inline-flex items-center gap-1 rounded-md bg-primary-50 px-2 py-1 text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                    <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" />
                    <span>{{ $text }}</span>
                    @if ($progress !== null)
                        <span class="font-medium">· {{ $progress }} %</span>
                    @endif
                </span>
            @elseif ($status === 'finished')
                <span class="inline-flex items-center gap-1 rounded-md bg-success-50 px-2 py-1 text-success-700 dark:bg-success-950 dark:text-success-300">
                    <x-heroicon-o-check-circle class="h-4 w-4" />
                    <span>{{ $text }}</span>
                </span>
            @elseif ($status === 'failed')
                <span class="inline-flex items-center gap-1 rounded-md bg-danger-50 px-2 py-1 text-danger-700 dark:bg-danger-950 dark:text-danger-300">
                    <x-heroicon-o-x-circle class="h-4 w-4" />
                    <span>{{ $text }}</span>
                </span>
            @endif
        </div>
    @endif
</div>
