@props([
    'label',
    'model',
    'center' => false,
])

<div
    x-data="{ filled: false }"
    x-init="filled = $refs.input.value !== ''"
    class="relative min-w-0"
>
    <input
        x-ref="input"
        wire:model.defer="{{ $model }}"
        @input="filled = $event.target.value !== ''"
        class="
            poof-input
            w-full
            {{ $center ? 'text-center' : '' }}
            text-sm
            pt-5
            transition-all duration-200
            border
        "
        :class="filled
            ? 'border-yellow-400 ring-1 ring-yellow-400/40'
            : 'border-gray-700'
        "
    >

    <label
        class="
            pointer-events-none
            absolute left-3 px-1
            text-xs font-medium
            transition-all duration-200
        "
        :class="filled
            ? '-top-2 text-[10px] text-yellow-400 bg-gray-950'
            : 'top-1/2 -translate-y-1/2 text-gray-500 bg-transparent'
        "
    >
        {{ $label }}
    </label>
</div>
