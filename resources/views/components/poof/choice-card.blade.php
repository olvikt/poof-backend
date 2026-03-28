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
@endphp

<div
    wire:click="$set('{{ $boundModel }}', '{{ $value }}')"
    class="
        flex-1 cursor-pointer
        flex items-center justify-between
        px-3 py-2 rounded-xl
        bg-neutral-900
        transition-all duration-150 active:scale-95
        {{ $current === $value
            ? 'border border-yellow-400 shadow-md'
            : 'border border-neutral-700 shadow-sm'
        }}
    "
>
    {{-- hidden radio for semantics --}}
    <input
        type="radio"
        wire:model="{{ $boundModel }}"
        value="{{ $value }}"
        class="hidden pointer-events-none"
    >

    <div class="flex items-center gap-4">
        {{-- indicator --}}
        <span
            class="
                w-5 h-5 rounded-full
                border-2 flex items-center justify-center
                {{ $current === $value
                    ? 'border-yellow-400'
                    : 'border-neutral-600'
                }}
            "
        >
            @if($current === $value)
                <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
            @endif
        </span>

        <div>
            <div class="text-sm font-semibold text-white">
                {{ $title }}
            </div>

            @if($subtitle)
                <div class="text-xs text-gray-400">
                    {{ $subtitle }}
                </div>
            @endif
        </div>
    </div>

    @if($icon)
        <span class="text-lg">{{ $icon }}</span>
    @endif
</div>
