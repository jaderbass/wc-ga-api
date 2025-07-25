<x-filament-widgets::widget>
  <x-filament::section>
    {{-- Icon --}}
    <x-heroicon-o-archive-box class="h-10 w-10 text-white" />

    {{-- Text & Zahl --}}
    <div>
      <h3 class="text-lg font-medium text-gray-100">Produkte gesamt</h3>
      <p class="text-4xl font-bold text-white">{{ $count }}</p>
    </div>
  </x-filament::section>
</x-filament-widgets::widget>
