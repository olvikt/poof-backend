@props([
    'label',
    'loadingLabel' => '⏳ Обробка…',
])

<button
    type="button"
    {{ $attributes->merge([
        'class' => 'w-full bg-yellow-400 text-black text-xl font-extrabold py-4 rounded-2xl active:scale-95 disabled:opacity-60'
    ]) }}
>
    <span wire:loading.remove>
        {{ $label }}
    </span>

    <span wire:loading>
        {{ $loadingLabel }}
    </span>
</button>
