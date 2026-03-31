<x-layouts.app title="Poof — Перенаправлення на оплату">
<div class="px-4 py-6">
    <div class="bg-gradient-to-b from-gray-900 to-gray-800 rounded-3xl p-6 border border-gray-700 shadow-xl space-y-6">
        <div class="text-center space-y-2">
            <div class="text-3xl">💳</div>
            <h2 class="text-xl font-semibold text-gray-200">Переходимо до оплати</h2>
            <p class="text-sm text-gray-400">Ви будете автоматично перенаправлені на захищену платіжну сторінку WayForPay.</p>
        </div>

        <form id="wayforpay-payment-form" method="POST" action="{{ $payUrl }}" class="space-y-4">
            @foreach($checkoutData as $field => $value)
                @if(is_array($value))
                    @foreach($value as $arrayValue)
                        <input type="hidden" name="{{ $field }}[]" value="{{ $arrayValue }}">
                    @endforeach
                @elseif($value !== null && $value !== '')
                    <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                @endif
            @endforeach

            <button type="submit" class="w-full py-4 rounded-2xl bg-yellow-400 text-black font-semibold">
                Якщо не перенаправило автоматично — натисніть тут
            </button>
        </form>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        const form = document.getElementById('wayforpay-payment-form');
        if (form) {
            form.submit();
        }
    });
</script>
</x-layouts.app>
