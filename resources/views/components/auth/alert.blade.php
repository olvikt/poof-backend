@props([
    'type' => 'info',
])

@php
    $styles = match ($type) {
        'success' => 'border-emerald-400/40 bg-emerald-500/15 text-emerald-100',
        'error' => 'border-rose-400/45 bg-rose-500/15 text-rose-50',
        default => 'border-sky-400/40 bg-sky-500/15 text-sky-50',
    };
@endphp

<div {{ $attributes->class([
    'rounded-xl border px-4 py-3 text-sm leading-6 shadow-sm backdrop-blur-sm',
    $styles,
]) }}>
    {{ $slot }}
</div>
