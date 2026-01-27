@props([
    'maxWidth' => 'max-w-sm',
])

<div
    x-data="{ open: @entangle($attributes->wire('model')) }"
    x-show="open"
    x-transition.opacity
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
>
    <div
        @click.outside="open = false"
        class="w-full {{ $maxWidth }} rounded-2xl bg-neutral-900 border border-neutral-700 shadow-xl p-6"
    >
        {{ $slot }}
    </div>
</div>
