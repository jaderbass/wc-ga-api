{{-- resources/views/dev/select-style-test.blade.php --}}
@extends('filament::page')

@section('title', 'Dropdown Style Test')

@section('content')
<div class="p-6 space-y-8">
    <h1 class="text-2xl font-bold">🎨 Filament Select Style Test</h1>

    {{-- Native Select (prüft unsere CSS-Overrides für .fi-select-input option) --}}
    <div class="space-y-2">
        <h2 class="text-lg font-semibold">Native Select</h2>
        <select class="fi-select-input block w-64 border border-gray-300 rounded-md py-2 px-3 bg-white dark:bg-gray-900 dark:text-gray-100">
            <option value="">Bitte wählen</option>
            <option value="1">Erste Option</option>
            <option value="2">Zweite Option</option>
            <option value="3">Dritte Option</option>
        </select>
        <p class="text-xs text-gray-500 dark:text-gray-400">Hover/Selected sollten klar lesbar sein (Light & Dark).</p>
    </div>

    {{-- Filament Tom Select über Livewire-Komponente --}}
    <livewire:dev.tom-select-demo />

    <div class="text-sm text-gray-500 dark:text-gray-400">
        💡 Tipp: Darkmode im Dashboard umschalten (oben rechts) und Hover/Selection testen.
    </div>
</div>
@endsection
