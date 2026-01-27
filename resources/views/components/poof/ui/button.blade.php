@props([
    'variant' => 'primary', // primary | ghost | danger
])

@php
$variants = [
    'primary' => 'bg-yellow-400 text-black font-black',
    'ghost'   => 'bg-yellow-400/10 text-yellow-400 border border-yellow-400/20',
    'danger'  => 'bg-red-500/10 text-red-400 border border-red-500/20',
];
@endphp

<button
    {{ $attributes->merge([
        'class' =>
            'w-full py-4 rounded-xl transition active:scale-[0.99] ' .
            ($variants[$variant] ?? $variants['primary'])
    ]) }}
>
    {{ $slot }}
</button>
