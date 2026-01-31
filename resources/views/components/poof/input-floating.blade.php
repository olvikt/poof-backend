@props([
    'label',
    'model',
    'center' => false,
    'live' => false,
])

@php
    $wireModelAttr = $live ? 'wire:model.live' : 'wire:model.defer';
@endphp

<div class="relative min-w-0">

    <input
        {{ $wireModelAttr }}="{{ $model }}"
        placeholder=" "
        class="
            peer
            poof-input
            w-full
            text-sm
            pt-5
            border
            transition-all duration-200

            {{ $center ? 'text-center' : '' }}

            border-gray-700
            focus:border-yellow-400
            focus:ring-1 focus:ring-yellow-400/40
        "
    >

    <label
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
