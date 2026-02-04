@props([
    'hint' => null,
])

<div>
    <div class="flex items-center justify-between mb-4">
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
        {{-- MAP --}}
        
		
		<div
    id="map"
    wire:ignore
    class="map-container w-full rounded-xl
           border border-neutral-700
           overflow-hidden bg-neutral-800 z-0"
    style="height: min(50vh, 420px);"
></div>

        {{-- STATUS / HINT --}}
        <div class="mt-2 text-xs text-gray-400 flex items-start gap-2">
            <span>ℹ️</span>

            <span>
                {{-- если передан кастомный hint — используем его --}}
                {{ $hint ?? 'Оберіть точку на мапі — вона потрібна для курʼєра та розрахунку маршруту.' }}
            </span>
        </div>
    </div>
</div>
