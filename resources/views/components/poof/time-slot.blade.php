@props([
    'active' => false,
    'disabled' => false,
])

<button
    type="button"
    {{ $attributes->merge([
        'class' => 'relative snap-start shrink-0 w-[125px] px-4 py-2 rounded-2xl transition-all duration-200 text-sm font-semibold'
    ]) }}
>
    {{ $slot }}
</button>
<div {{ $attributes->merge([
    'class' => '
        relative shrink-0 w-28 px-4 py-2 rounded-2xl
        text-sm font-semibold
        transition-all duration-300
        snap-start
    '
]) }}>
    {{ $slot }}
</div>