<div class="min-h-screen rounded-xl bg-gray-950 px-4 pb-28 pt-4 text-white shadow-[0_0_0_1px_rgba(74,222,128,0.25)]">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Оплата</h1>
            <p class="mt-1 text-sm text-gray-400">Ваші витрати та історія платежів.</p>
        </div>
        <a href="{{ route('client.home', ['open_more' => 1]) }}" class="rounded-xl border border-gray-700 px-3 py-2 text-sm text-gray-200">Закрити</a>
    </div>

    <section class="mt-4 grid grid-cols-2 gap-3 text-sm">
        <div class="rounded-2xl border border-gray-800 bg-gray-900 p-3">
            <p class="text-gray-400">Витрачено всього</p>
            <p class="mt-1 text-xl font-bold text-yellow-400">{{ number_format($stats['total_spent'], 0, ',', ' ') }} ₴</p>
        </div>
        <div class="rounded-2xl border border-gray-800 bg-gray-900 p-3">
            <p class="text-gray-400">Витрачено за місяць</p>
            <p class="mt-1 text-xl font-bold text-yellow-400">{{ number_format($stats['month_spent'], 0, ',', ' ') }} ₴</p>
        </div>
        <div class="rounded-2xl border border-gray-800 bg-gray-900 p-3">
            <p class="text-gray-400">Оплачено замовлень</p>
            <p class="mt-1 text-xl font-bold text-yellow-400">{{ $stats['paid_orders'] }}</p>
        </div>
        <div class="rounded-2xl border border-gray-800 bg-gray-900 p-3">
            <p class="text-gray-400">Оплачено підписок</p>
            <p class="mt-1 text-xl font-bold text-yellow-400">{{ $stats['paid_subscriptions'] }}</p>
        </div>
    </section>

    <div class="mt-3 rounded-2xl border border-gray-800 bg-gray-900 p-3 text-sm text-gray-300">
        @if($stats['last_payment'])
            Останній платіж: <span class="font-semibold text-white">{{ $stats['last_payment']->created_at?->format('d.m.Y H:i') }}</span>
            · {{ number_format((int) (($stats['last_payment']->client_charge_amount > 0) ? $stats['last_payment']->client_charge_amount : $stats['last_payment']->price), 0, ',', ' ') }} ₴
        @else
            Останній платіж: —
        @endif
    </div>

    <section class="mt-5 space-y-3">
        @forelse($operations as $operation)
            <article class="rounded-2xl border border-gray-800 bg-gray-900 p-4 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-white">{{ $operation['type'] }}</p>
                        <p class="text-xs text-gray-400">{{ optional($operation['created_at'])->format('d.m.Y H:i') }}</p>
                    </div>
                    <p class="text-base font-bold text-yellow-400">{{ number_format($operation['amount'], 0, ',', ' ') }} ₴</p>
                </div>

                <div class="mt-2 flex items-center justify-between text-xs">
                    <span class="text-gray-300">{{ $operation['status_label'] }}</span>
                    <span class="text-gray-500">Замовлення #{{ $operation['id'] }}@if($operation['subscription_id']) · Підписка #{{ $operation['subscription_id'] }}@endif</span>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-700 bg-gray-900/70 p-4 text-sm text-gray-300">
                Платежів поки немає.
            </div>
        @endforelse
    </section>
</div>
