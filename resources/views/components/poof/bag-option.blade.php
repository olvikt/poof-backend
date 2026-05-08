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
    data-e2e="bag-option-{{ $count }}"
    class="
        relative flex-1 w-full
        px-4 py-4 rounded-2xl border
        transition-all duration-200 active:scale-[0.98]
        text-center
        {{ $active && ! $disabled
            ? 'bg-gradient-to-br from-yellow-300 to-yellow-400 text-black border-yellow-300/70 shadow-lg shadow-yellow-500/10'
            : 'bg-neutral-900/80 text-white border-neutral-700'
        }}
        {{ $disabled ? 'bg-neutral-800/70 text-gray-400 border-neutral-700 cursor-not-allowed' : 'cursor-pointer' }}
    "
>
    <div class="mb-2 flex items-center justify-center {{ $count > 1 ? '-space-x-1' : '' }}" data-e2e="bag-icons">
        @for($i = 0; $i < $count; $i++)
            <x-poof.icons.bag class="h-7 w-7" />
        @endfor
    </div>

    <div class="mt-2 text-xs {{ $active ? 'text-black/70' : 'text-gray-300' }}">{{ UaPlural::bags($count) }}</div>
    <div class="mt-2 text-xl font-semibold tracking-tight {{ $active ? 'text-black' : 'text-white' }}">{{ $price }} ₴</div>

    @if($disabled)
        <div class="absolute bottom-2 right-3 text-[11px] text-gray-500">Недоступно</div>
    @endif
</button>
