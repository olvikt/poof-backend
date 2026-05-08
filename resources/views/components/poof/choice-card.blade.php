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
        flex-1 cursor-pointer min-h-[88px]
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

    <div class="flex items-center gap-3 min-w-0">
        <span class="h-5 w-5 rounded-full border flex items-center justify-center shrink-0 {{ $isActive ? 'border-yellow-400 ring-2 ring-yellow-400/40' : 'border-neutral-500' }}">
            @if($isActive)
                <span class="h-2 w-2 rounded-full bg-yellow-400"></span>
            @endif
        </span>

        @if($icon)
            <span class="flex h-7 w-7 items-center justify-center shrink-0 {{ $isActive ? 'text-yellow-400 opacity-100' : 'text-gray-300 opacity-80' }}" data-e2e="icon-{{ $icon }}">
                @switch($icon)
                    @case('door')
                        <x-poof.icons.door class="h-5 w-5" />
                        @break
                    @case('handshake')
                        <x-poof.icons.handshake class="h-5 w-5" />
                        @break
                @endswitch
            </span>
        @endif

        <div class="min-w-0">
            <div class="text-sm font-semibold text-white leading-tight">{{ $title }}</div>
            @if($subtitle)
                <div class="mt-1 text-xs text-gray-400 leading-snug">{{ $subtitle }}</div>
            @endif
        </div>
    </div>
</div>
