@props([
    'days',
    'active' => false,
    'disabled' => false,
])

<div
    class="
        flex-1 cursor-pointer
        px-4 py-4 rounded-2xl
        text-center
        transition-all duration-150 active:scale-95
        {{ $active && ! $disabled
            ? 'bg-gradient-to-b from-green-300 to-green-400 text-black shadow-lg'
            : 'bg-neutral-800 text-gray-200 border border-neutral-700 shadow-sm'
        }}
        {{ $disabled ? 'opacity-50 pointer-events-none' : '' }}
    "
>
@use(App\Support\UaPlural)

<div class="text-lg font-extrabold">
    {{ $days }} {{ UaPlural::days($days) }}
</div>

    <div class="text-xs opacity-80">
        безкоштовно
    </div>
</div>
