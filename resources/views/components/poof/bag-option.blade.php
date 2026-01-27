@props([
    'count',
    'price',
    'active' => false,
    'disabled' => false,
])

@use(App\Support\UaPlural)

<div
    class="
        flex-1 cursor-pointer
        px-3 py-3 rounded-2xl
        transition-all duration-150 active:scale-95
        text-center
        {{ $active && ! $disabled
            ? 'bg-gradient-to-b from-yellow-300 to-yellow-400 text-black shadow-lg'
            : 'bg-neutral-900 text-gray-200 border border-neutral-700 shadow-sm'
        }}
        {{ $disabled ? 'opacity-50 pointer-events-none' : '' }}
    "
>
    <div class="text-lg font-extrabold">
        {{ $count }}
    </div>

    <div class="text-xs opacity-80">
        {{ UaPlural::bags($count) }}
    </div>

    <div class="mt-2 text-xl font-semibold">
        {{ $price }} ₴
    </div>
</div>
