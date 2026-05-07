@props([
    'value',
    'current',
    'model' => null,
    'title',
    'subtitle' => null,
    'icon' => null,
])

@php
    $boundModel = $model ?: $attributes->wire('model')->value();
    $isActive = $current === $value;
@endphp

<div
    wire:click="$set('{{ $boundModel }}', '{{ $value }}')"
    class="
        flex-1 cursor-pointer min-h-[72px] md:min-h-[80px]
        flex items-center justify-between
        rounded-2xl border bg-neutral-900/80 px-4 py-4
        transition-all duration-200 active:scale-[0.98]
        {{ $isActive
            ? 'border-yellow-400 ring-1 ring-yellow-400/30'
            : 'border-neutral-700 hover:border-neutral-600'
        }}
    "
>
    <input type="radio" wire:model="{{ $boundModel }}" value="{{ $value }}" class="hidden pointer-events-none">

    <div class="flex items-center gap-3">
        <span class="h-[18px] w-[18px] rounded-full border flex items-center justify-center {{ $isActive ? 'border-yellow-400 ring-2 ring-yellow-400/20' : 'border-gray-500' }}">
            @if($isActive)
                <span class="h-2 w-2 rounded-full bg-yellow-400"></span>
            @endif
        </span>

        <div class="flex items-center gap-3">
            @if($icon)
                <span class="{{ $isActive ? 'text-yellow-400' : 'text-gray-300' }}" data-e2e="icon-{{ $icon }}">
                    @switch($icon)
                        @case('door')
                            <x-poof.icons.door class="h-6 w-6" />
                            @break
                        @case('handshake')
                            <x-poof.icons.handshake class="h-6 w-6" />
                            @break
                    @endswitch
                </span>
            @endif

            <div>
                <div class="text-sm font-semibold text-white leading-tight">{{ $title }}</div>
                @if($subtitle)
                    <div class="text-xs text-gray-400 mt-1">{{ $subtitle }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
