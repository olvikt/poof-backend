@props([
    'maxWidth' => 'max-w-sm',
])

@php
    $model = $attributes->wire('model')->value();
@endphp

<div
    x-data
    x-show="$wire.{{ $model }}"
    x-transition.opacity
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
>
    <div
        @click.outside="$wire.set('{{ $model }}', false)"
        class="w-full {{ $maxWidth }} rounded-2xl bg-neutral-900 border border-neutral-700 shadow-xl p-6"
    >
        {{ $slot }}
    </div>
</div>
