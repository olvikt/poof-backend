@props([
    'type' => 'button',
    'href' => null,
    'variant' => 'primary',   // primary | dark | ghost
    'size' => 'md',           // sm | md | lg
    'active' => false,
    'disabled' => false,
])

@php
    $base = 'inline-flex items-center justify-center font-semibold rounded-2xl transition-all duration-150 active:scale-95 select-none';

    $sizes = [
        'sm' => 'text-sm px-3 py-1.5 rounded-lg',
        'md' => 'text-sm px-4 py-2 rounded-2xl',
        'lg' => 'text-base px-5 py-3 rounded-2xl',
    ];

    // По умолчанию — твой стиль (желтая кнопка)
    $variants = [
        'primary' => 'bg-yellow-400 text-black',
        'dark'    => 'bg-neutral-900 text-gray-200 border border-neutral-800 shadow-sm',
        'ghost'   => 'bg-transparent text-gray-200 hover:bg-white/5',
    ];

    // Если active=true, делаем как “selected”
    $activeClass = 'bg-gradient-to-b from-yellow-300 to-yellow-400 text-black shadow-lg';

    $cls = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);

    if ($active) $cls = $base.' '.($sizes[$size] ?? $sizes['md']).' '.$activeClass;

    if ($disabled) $cls .= ' opacity-60 pointer-events-none';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $cls]) }}>
        {{ $slot }}
    </button>
@endif