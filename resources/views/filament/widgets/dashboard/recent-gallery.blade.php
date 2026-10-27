<x-filament-widgets::widget>
    @include('filament.widgets.dashboard._styles')

    <x-filament::section heading="Zuletzt hinzugefügt" description="Die neuesten Produkte mit Bild">
        <div class="psa-gallery">
            @foreach ($products as $product)
                <a class="psa-card" href="{{ $editUrl($product['id']) }}">
                    <div class="img">
                        <img src="{{ $product['image'] }}" alt="" loading="lazy" referrerpolicy="no-referrer">
                    </div>
                    <div class="txt">
                        <b>{{ $product['manufacturer'] }}</b>
                        {{ \Illuminate\Support\Str::limit($product['name'], 60) }}
                    </div>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
