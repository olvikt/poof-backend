@props([
    'title',
    'subtitle' => null,
    'active' => false,
    'disabled' => false,
    'used' => false,
    'activeClass' => 'border-yellow-400 ring-1 ring-yellow-400/30 bg-neutral-900/90 text-white',
    'badge' => null,
    'badgeClass' => 'bg-yellow-300/90 text-yellow-950',
    'icon' => null,
    'marker' => null,
    'trailing' => false,
    'containerClass' => '',
    'iconClass' => '',
    'subtitleClass' => '',
    'compact' => false,
    'hideSubtitle' => false,
])

<div
    @if($marker) data-e2e="{{ $marker }}" @endif
    class="
        group relative rounded-2xl border text-left {{ $compact ? 'min-h-[74px] px-4 py-3 flex items-center' : 'p-4 min-h-[112px]' }} {{ $containerClass }}
        transition-all duration-200 active:scale-[0.98]
        {{ $active && ! $disabled ? $activeClass : ($icon === 'calendar'
            ? 'border-neutral-700 bg-neutral-900/90 text-gray-100 hover:border-emerald-500/40 shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]'
            : 'border-neutral-700 bg-neutral-900/80 text-gray-100 hover:border-neutral-600') }}
        {{ $disabled ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer' }}
    "
>
    @if($badge)
        <span class="absolute right-3 top-0.5 rounded-full px-2 py-[2px] text-[10px] font-bold leading-none {{ $badgeClass }} max-w-fit">{{ $badge }}</span>
    @endif

    <div class="{{ $compact ? 'flex items-center gap-3 w-full' : 'flex items-start justify-center gap-3' }}">
        @if($icon)
            <div class="shrink-0 {{ $icon === 'calendar' ? 'text-emerald-400 opacity-90' : 'text-yellow-400' }}" data-e2e="icon-{{ $icon }}">
                @switch($icon)
                    @case('calendar')
                        <x-poof.icons.calendar class="{{ $compact ? 'h-7 w-7' : 'h-7 w-7' }} {{ $iconClass }}" />
                        @break
                    @case('gift')
                        <x-poof.icons.gift class="{{ $compact ? 'h-7 w-7' : 'h-12 w-12' }} {{ $iconClass }}" />
                        @break
                    @case('one-time')
                        <x-poof.icons.one-time class="h-7 w-7 {{ $iconClass }}" />
                        @break
                @endswitch
            </div>
        @endif

        <div class="flex-1 min-w-0 {{ $compact ? '' : 'pr-2' }}">
            <div class="{{ $compact ? 'text-sm sm:text-base font-semibold leading-tight' : 'text-base font-semibold leading-snug' }} {{ $badge ? ($compact ? 'pr-4' : ($icon === 'calendar' ? 'pr-24' : 'pr-16')) : '' }} text-white">
                {{ $title }}
            </div>
            @if(! $hideSubtitle && ($used || $subtitle))
                <div class="mt-1.5 text-xs sm:text-sm leading-snug {{ $compact ? '' : 'line-clamp-2' }} {{ $icon === 'calendar' ? 'text-gray-300' : 'text-gray-400' }} {{ $subtitleClass }}">
                    {{ $used ? 'Уже використано' : $subtitle }}
                </div>
            @endif
        </div>
    </div>

    @if($trailing)
        <div class="absolute right-4 top-1/2 -translate-y-1/2 text-lg leading-none text-gray-400/70 transition-all duration-200 group-hover:text-yellow-400 group-hover:opacity-100">→</div>
    @endif
</div>
