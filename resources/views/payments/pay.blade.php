<x-layouts.app title="Poof — Оплата">

<div class="px-4 py-6">

<div class="bg-gradient-to-b from-gray-900 to-gray-800 rounded-3xl p-6 border border-gray-700 shadow-xl space-y-6">

{{-- Header --}}
<div class="text-center space-y-1">

<div class="text-3xl">💳</div>

<h2 class="text-xl font-semibold text-gray-200">
Оплата замовлення
</h2>

<p class="text-gray-400 text-xl">
№ {{ $order->id }}
</p>

</div>

{{-- Amount card --}}
<div class="bg-gray-800 rounded-2xl p-6 text-center relative overflow-hidden">

<div class="absolute inset-0 bg-gradient-to-r from-yellow-400/10 via-yellow-400/20 to-yellow-400/10 blur-xl"></div>

<p class="text-gray-400 text-sm relative">
Сума до сплати
</p>

<div class="text-5xl font-bold text-yellow-400 mt-2 relative">
{{ number_format((float) $order->price, 2, '.', ' ') }} ₴
</div>

</div>

{{-- Payment method --}}
<div class="bg-gray-800 rounded-xl p-4 flex items-center justify-between">

<div class="flex items-center gap-3">

<div class="text-xl">💳</div>

<div>

<p class="text-sm  text-gray-200  font-medium">
Картка
</p>

<p class="text-xs text-gray-400">
WayForPay / Apple Pay / Google Pay
</p>

</div>

</div>

<div class="text-green-400 text-sm">
доступно
</div>

</div>

{{-- Security --}}
<div class="flex items-center justify-center gap-2 text-xs text-gray-400">

<span>🔒</span>

<span>Безпечна карткова оплата</span>

</div>

{{-- Pay button --}}
<form method="POST" action="{{ route('client.payments.start', $order) }}">
@csrf

<button
class="w-full py-4 rounded-2xl bg-green-500 hover:bg-green-400 active:scale-95 transition text-lg font-semibold shadow-lg shadow-green-500/30">

✅ Оплатити зараз

</button>

</form>

@if($devFallbackEnabled)
<form method="POST" action="{{ route('client.payments.dev-pay', $order) }}" class="mt-3">
@csrf
<button
class="w-full py-3 rounded-2xl border border-yellow-400/50 text-yellow-300 text-sm font-medium hover:bg-yellow-400/10 transition">
Dev fallback: підтвердити оплату без еквайрингу
</button>
</form>
@endif

{{-- Back --}}
<a href="{{ route('client.orders') }}"
class="block w-full text-center py-3 rounded-2xl bg-yellow-500 hover:bg-yellow-400 text-black font-semibold transition">

← До замовлень

</a>

</div>

</div>

</x-layouts.app>
