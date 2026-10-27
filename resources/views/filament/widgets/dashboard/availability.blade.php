<x-filament-widgets::widget>
    @include('filament.widgets.dashboard._styles')

    @php
        $n = fn (int $v): string => number_format($v, 0, ',', '.');
        $share = fn (int $part, int $all): float => $all > 0 ? round(100 * $part / $all, 1) : 0;
        $all = $a['sellable'] + $a['offline'];
        $petzl = $a['petzl_in_stock'] + $a['petzl_out'];
    @endphp

    <x-filament::section heading="Lagerampel" description="Verfügbarkeit laut Lieferanten">
        <div class="psa-lights">
            <div>
                <div style="font-weight: 700">Alle Produkte</div>
                <div class="psa-stack">
                    <span style="width: {{ $share($a['sellable'], $all) }}%; background: #22c55e"></span>
                    <span style="width: {{ $share($a['offline'], $all) }}%; background: #ef4444"></span>
                </div>
                <div class="psa-legend">
                    <span><i style="background: #22c55e"></i>lieferbar {{ $n($a['sellable']) }}</span>
                    <span><i style="background: #ef4444"></i>ausverkauft / ausgelaufen {{ $n($a['offline']) }}</span>
                </div>
            </div>

            @if ($petzl > 0)
                <div>
                    <div style="font-weight: 700">Petzl-Händlerportal (Varianten)</div>
                    <div class="psa-stack">
                        <span style="width: {{ $share($a['petzl_in_stock'], $petzl) }}%; background: #22c55e"></span>
                        <span style="width: {{ $share($a['petzl_out'], $petzl) }}%; background: #eab308"></span>
                    </div>
                    <div class="psa-legend">
                        <span><i style="background: #22c55e"></i>auf Lager {{ $n($a['petzl_in_stock']) }}</span>
                        <span><i style="background: #eab308"></i>nicht auf Lager {{ $n($a['petzl_out']) }}</span>
                    </div>
                    @if ($a['petzl_read_at'])
                        <div class="psa-small">Stand: {{ \Illuminate\Support\Carbon::parse($a['petzl_read_at'])->format('d.m.Y') }}</div>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
