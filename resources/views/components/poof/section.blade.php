@props([
    'title',
])

<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    <label class="text-sm text-gray-400 mb-3 block">
        {{ $title }}
    </label>

    {{ $slot }}
</div>
