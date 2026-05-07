@props([
    'count',
    'price',
    'active' => false,
    'disabled' => false,
])

@use(App\Support\UaPlural)

<button
    {{ $attributes->merge(['type' => 'button']) }}
    @disabled($disabled)
    class="
        flex-1 w-full
        px-3 py-3 rounded-2xl
        transition-all duration-150 active:scale-95
        text-center
        {{ $active && ! $disabled
            ? 'bg-gradient-to-b from-yellow-300 to-yellow-400 text-black shadow-lg'
            : 'bg-neutral-900 text-gray-200 border border-neutral-700 shadow-sm'
        }}
        {{ $disabled ? 'bg-neutral-800 text-gray-500 border-neutral-700 opacity-70 cursor-not-allowed' : 'cursor-pointer' }}
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

    @if($disabled)
        <div class="mt-1 text-[11px] text-gray-500">🔒 Недоступно</div>
    @endif
</button>
