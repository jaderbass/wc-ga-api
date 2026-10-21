<x-filament-panels::page>
    <x-filament::section>
        @if ($locked)
            <div class="flex items-center gap-3">
                <x-filament::badge color="danger" size="lg">Abgeschaltet</x-filament::badge>
                <span>
                    seit {{ isset($info['locked_at']) ? \Illuminate\Support\Carbon::parse($info['locked_at'])->timezone(config('app.timezone'))->format('d.m.Y H:i') : '–' }} Uhr
                </span>
            </div>

            <ul class="mt-4 list-disc space-y-1 ps-5 text-sm text-gray-600 dark:text-gray-400">
                <li>Nur dieses Konto hat Zugang zum Backend.</li>
                <li>Webhooks und Schnittstellen antworten mit „nicht verfügbar“ (503).</li>
                <li>Hintergrundaufgaben (Warteschlange) pausieren, Artisan führt nur Wartungsbefehle aus.</li>
            </ul>
        @else
            <div class="flex items-center gap-3">
                <x-filament::badge color="success" size="lg">Eingeschaltet</x-filament::badge>
                <span>Normaler Betrieb.</span>
            </div>
        @endif

        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            Notfall auf dem Server: <code>php artisan system:lock</code> (Status), <code>… off</code> (abschalten), <code>… on</code> (einschalten).
        </p>
    </x-filament::section>
</x-filament-panels::page>
