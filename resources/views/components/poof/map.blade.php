@props([
    'hint' => 'Натисніть на карту або введіть адресу вручну',
])

<div>
    <div class="flex items-center justify-between mb-2">
        <span class="font-semibold">
            {{ $slot }}
        </span>

        <x-poof.button
            type="button"
            id="use-location-btn"
            size="sm"
        >
            📍 Моя локація
        </x-poof.button>
    </div>

    <div class="relative">
        <div
            id="map"
            wire:ignore
            class="h-[400px] w-full rounded-xl border border-neutral-700 overflow-hidden bg-neutral-800 z-0"
        ></div>

        <p class="text-xs text-gray-400 mt-1">
            {{ $hint }}
        </p>
    </div>
</div>
