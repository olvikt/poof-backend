@props([
    'type' => 'info',
])

@php
    $styles = match ($type) {
        'success' => 'border-lime-400/60 bg-lime-500/20 text-lime-200',
        'error' => 'border-orange-400/60 bg-orange-500/20 text-orange-100',
        default => 'border-sky-400/50 bg-sky-500/20 text-sky-100',
    };
@endphp

<div {{ $attributes->class([
    'rounded-xl border px-4 py-3 text-sm leading-6 shadow-sm backdrop-blur-sm',
    $styles,
]) }}>
    {{ $slot }}
</div>
