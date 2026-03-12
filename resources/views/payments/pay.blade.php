<div class="min-h-screen bg-gray-900 text-white p-4 sm:p-6 flex items-center justify-center">
    <div class="w-full max-w-md mx-auto">
        <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 shadow-lg space-y-6">
            <h1 class="text-xl font-semibold text-white text-center">
                💳 Оплата замовлення #{{ $order->id }}
            </h1>

            <div class="text-center space-y-1">
                <p class="text-sm text-gray-400">Сума до сплати</p>
                <p class="text-3xl font-bold text-yellow-400">
                    ₴ {{ number_format((float) $order->price, 2, '.', ' ') }}
                </p>
            </div>

            <form method="POST" action="{{ route('client.payments.dev-pay', $order) }}" class="w-full">
                @csrf
                <button
                    type="submit"
                    class="w-full p-4 rounded-xl bg-green-500 hover:bg-green-400 text-white font-semibold text-lg transition"
                >
                    Сплатити через LiqPay
                </button>
            </form>

            <p class="text-center text-sm text-gray-400">або</p>

            <a
                href="{{ route('client.orders') }}"
                class="block w-full text-center p-4 rounded-xl bg-yellow-500 hover:bg-yellow-400 text-black font-semibold transition"
            >
                Повернутися до замовлення
            </a>
        </div>
    </div>
</div>
