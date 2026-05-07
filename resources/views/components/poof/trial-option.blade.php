@props([
    'title',
    'subtitle',
    'active' => false,
    'disabled' => false,
    'used' => false,
    'activeClass' => 'border-green-300 bg-gradient-to-b from-green-300 to-green-400 text-black shadow-lg',
    'badge' => null,
    'badgeClass' => 'bg-yellow-300 text-yellow-900',
    'icon' => null,
    'marker' => null,
    'trailing' => false,
])

<div
    @if($marker) data-e2e="{{ $marker }}" @endif
    class="
        relative flex min-h-[112px] flex-col justify-between
        rounded-2xl border px-4 py-4 text-left
        transition-all duration-150 active:scale-95
        {{ $active && ! $disabled
            ? $activeClass
            : 'border-neutral-700 bg-neutral-800 text-gray-100 shadow-sm'
        }}
        {{ $disabled ? 'opacity-60' : 'cursor-pointer' }}
    "
>
    @if($badge)
        <span class="absolute right-3 top-3 rounded-full px-2 py-1 text-[10px] font-bold {{ $badgeClass }}">{{ $badge }}</span>
    @endif

    <div class="flex items-start justify-between gap-3 pr-16">
        <div>
            <div class="text-sm font-bold leading-tight">{{ $title }}</div>
            <div class="mt-1 text-xs opacity-80">{{ $used ? 'Уже використано' : $subtitle }}</div>
        </div>
        @if($icon)
            <div class="shrink-0 {{ $active ? 'text-yellow-900' : 'text-gray-300' }}">{{ $icon }}</div>
        @endif
    </div>

    @if($trailing)
        <div class="mt-3 text-right text-sm opacity-80">→</div>
    @endif
</div>
