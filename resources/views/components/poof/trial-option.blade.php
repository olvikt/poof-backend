@props([
    'title',
    'subtitle',
    'active' => false,
    'disabled' => false,
    'used' => false,
])

<div
    class="
        flex min-h-[112px] flex-col justify-between
        rounded-2xl border px-4 py-4 text-left
        transition-all duration-150 active:scale-95
        {{ $active && ! $disabled
            ? 'border-green-300 bg-gradient-to-b from-green-300 to-green-400 text-black shadow-lg'
            : 'border-neutral-700 bg-neutral-800 text-gray-100 shadow-sm'
        }}
        {{ $disabled ? 'opacity-60' : 'cursor-pointer' }}
    "
>
    <div class="text-sm font-bold leading-tight">
        {{ $title }}
    </div>

    <div class="text-xs opacity-80">
        @if($used)
            Уже використано
        @else
            {{ $subtitle }}
        @endif
    </div>
</div>
