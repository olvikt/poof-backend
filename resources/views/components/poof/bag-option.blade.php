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
            ? 'bg-gradient-to-br from-yellow-300 to-yellow-500 text-black border-yellow-400 shadow-lg shadow-yellow-500/10'
            : 'bg-neutral-900/80 text-gray-200 border-neutral-700'
        }}
        {{ $disabled ? 'bg-neutral-800/70 text-gray-500 border-neutral-700 cursor-not-allowed' : 'cursor-pointer' }}
    "
>
    <div class="flex items-center justify-center {{ $count > 1 ? '-space-x-2' : '' }}" data-e2e="bag-icons">
        @for($i = 0; $i < $count; $i++)
            <x-poof.icons.bag class="h-8 w-8" />
        @endfor
    </div>

    <div class="mt-2 text-xs opacity-80">{{ UaPlural::bags($count) }}</div>
    <div class="mt-2 text-xl font-semibold">{{ $price }} ₴</div>

    @if($disabled)
        <div class="absolute bottom-2 right-3 text-[11px] text-gray-500">Недоступно</div>
    @endif
</button>
