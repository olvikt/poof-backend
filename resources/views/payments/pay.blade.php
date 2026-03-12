@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-900 text-white p-4">
    <div class="w-full max-w-md bg-neutral-900 border border-neutral-800 rounded-2xl p-6 space-y-6 shadow-lg">
        <h1 class="text-xl font-semibold text-center">
            💳 Оплата замовлення #{{ $order->id }}
        </h1>

        <div class="text-center">
            <p class="text-gray-400 text-sm">Сума до сплати</p>

            <div class="text-3xl font-bold text-yellow-400 mt-1">
                ₴ {{ number_format((float) $order->price, 2, '.', ' ') }}
            </div>
        </div>

        <a
            href="{{ $liqpayUrl }}"
            class="block w-full text-center p-4 rounded-xl bg-green-500 hover:bg-green-400 text-white font-semibold text-lg transition"
        >
            Сплатити через LiqPay
        </a>

        <a
            href="{{ route('client.orders.show', $order) }}"
            class="block w-full text-center p-4 rounded-xl bg-yellow-500 hover:bg-yellow-400 text-black font-semibold transition"
        >
            Повернутися до замовлення
        </a>
    </div>
</div>
@endsection
