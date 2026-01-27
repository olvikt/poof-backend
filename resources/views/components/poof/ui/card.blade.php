@props([
    'class' => '',
])

<div {{ $attributes->merge([
    'class' => 'rounded-2xl border border-gray-800 bg-gray-900/40 p-4 ' . $class
]) }}>
    {{ $slot }}
</div>
