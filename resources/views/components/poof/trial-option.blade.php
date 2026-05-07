@props([
    'title',
    'subtitle',
    'active' => false,
    'disabled' => false,
    'used' => false,
    'activeClass' => 'border-yellow-400 ring-1 ring-yellow-400/30 bg-neutral-800/90 text-white',
    'badge' => null,
    'badgeClass' => 'bg-yellow-300/90 text-yellow-950',
    'icon' => null,
    'marker' => null,
    'trailing' => false,
])

<div
    @if($marker) data-e2e="{{ $marker }}" @endif
    class="
        relative flex min-h-[88px] md:min-h-[96px] flex-col justify-center
        rounded-2xl border p-5 md:p-6 text-left
        transition-all duration-200 active:scale-[0.98]
        {{ $active && ! $disabled ? $activeClass : 'border-neutral-700 bg-neutral-900/80 text-gray-100 hover:border-neutral-600' }}
        {{ $disabled ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer' }}
    "
>
    @if($badge)
        <span class="absolute right-3 top-3 rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $badgeClass }}">{{ $badge }}</span>
    @endif

    <div class="relative flex items-start gap-3 pr-20">
        @if($icon)
            <div class="shrink-0 {{ $active ? 'text-black' : ($icon === 'calendar' ? 'text-emerald-400' : 'text-yellow-400') }}" data-e2e="icon-{{ $icon }}">
                @switch($icon)
                    @case('calendar')
                        <x-poof.icons.calendar class="h-8 w-8" />
                        @break
                    @case('gift')
                        <x-poof.icons.gift class="h-8 w-8" />
                        @break
                    @case('one-time')
                        <x-poof.icons.one-time class="h-8 w-8" />
                        @break
                @endswitch
            </div>
        @endif

        <div class="max-w-[16rem]">
            <div class="text-base font-semibold leading-tight">{{ $title }}</div>
            <div class="mt-1.5 text-sm text-gray-400 {{ $active ? 'text-black/80' : '' }}">{{ $used ? 'Уже використано' : $subtitle }}</div>
        </div>

        @if($icon === 'one-time')
            <div class="pointer-events-none absolute bottom-0 right-3 opacity-20 {{ $active ? 'text-black' : 'text-yellow-400' }}">
                <x-poof.icons.one-time class="h-10 w-10" />
            </div>
        @endif
    </div>

    @if($trailing)
        <div class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 transition-colors duration-200 group-hover:text-yellow-400">→</div>
    @endif
</div>
