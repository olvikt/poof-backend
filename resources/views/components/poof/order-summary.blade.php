@props([
    'price',
    'isTrial' => false,
])

<div
    x-data="{ value: {{ $price }} }"
    x-effect="
        if (value !== {{ $price }}) {
            value = {{ $price }};
            $el.classList.add('scale-105');
            setTimeout(() => $el.classList.remove('scale-105'), 150);
        }
    "
    class="
        transition-transform duration-150
        mb-3
    "
>
    <div
        class="
            flex items-center justify-between
            px-5 py-4 rounded-2xl
            bg-neutral-900
            border border-neutral-700
        "
    >
        <span class="text-sm text-gray-400">
            До оплати
        </span>

        <div class="text-right">
            <div class="text-2xl font-extrabold text-yellow-400">
                {{ $price }} ₴
            </div>

            @if($isTrial)
                <div class="text-xs text-green-400">
                    тестовий період
                </div>
            @endif
        </div>
    </div>
</div>
