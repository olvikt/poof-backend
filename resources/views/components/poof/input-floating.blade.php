@props([
    'label',
    'model',
    'center' => false,
    'live' => false,
    'inputmode' => null,
    'pattern' => null,
])

@php
    $wireModelAttr = $live ? 'wire:model.live' : 'wire:model.defer';
    $inputId = $attributes->get('id')
        ?? 'poof-input-'.\Illuminate\Support\Str::slug($model.'-'.$label, '-');
@endphp

<div class="relative min-w-0">

    <input
        id="{{ $inputId }}"
        {{ $wireModelAttr }}="{{ $model }}"
        placeholder=" "
        @if($inputmode) inputmode="{{ $inputmode }}" @endif
        @if($pattern) pattern="{{ $pattern }}" @endif
        {{ $attributes->class([
            'peer',
            'poof-input',
            'w-full',
            'text-sm',
            'pt-5',
            'border',
            'transition-all duration-200',
            'text-center' => $center,
            'border-gray-700',
            'focus:border-yellow-400',
            'focus:ring-1 focus:ring-yellow-400/40',
        ]) }}
    >

    <label
        for="{{ $inputId }}"
        class="
            pointer-events-none
            absolute left-3 px-1
            bg-gray-950

            text-xs font-medium
            transition-all duration-200

            /* 🔑 ОСНОВА FLOATING */
            top-1/2 -translate-y-1/2 text-gray-500

            peer-focus:-top-2
            peer-focus:translate-y-0
            peer-focus:text-[10px]
            peer-focus:text-yellow-400

            peer-[&:not(:placeholder-shown)]:-top-2
            peer-[&:not(:placeholder-shown)]:translate-y-0
            peer-[&:not(:placeholder-shown)]:text-[10px]
            peer-[&:not(:placeholder-shown)]:text-yellow-400
        "
    >
        {{ $label }}
    </label>

</div>
