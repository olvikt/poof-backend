<!--<div class="shadow-[0_0_0_1px_rgba(74,222,128,0.25)] min-h-screen bg-gradient-to-r from-poof-400 via-yellow-300 to-poof-400 text-black px-4 pt-4 pb-28 rounded-xl">-->

<div class="shadow-[0_0_0_1px_rgba(74,222,128,0.25)]
            min-h-screen bg-gray-950 text-white
            px-4 pt-4 pb-28 rounded-xl">

    {{-- TITLE --}}
    <h1 class="text-lg font-semibold mb-4">
        Мої замовлення
    </h1>

    @if($cancelFeedback)
        <div
            class="mb-4 rounded-lg border px-4 py-3 text-sm
                {{ $cancelFeedbackType === 'success'
                    ? 'border-green-400/40 bg-green-500/10 text-green-300'
                    : 'border-red-400/40 bg-red-500/10 text-red-300' }}"
        >
            {{ $cancelFeedback }}
        </div>
    @endif

    @if($paymentStatus === 'success' && $showPaymentSuccessModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4" wire:key="payment-success-modal">
            <div class="w-full max-w-sm rounded-2xl border border-green-400/25 bg-gray-900 p-5 shadow-2xl">
                <x-poof.icons.success-check />

                <h2 class="mt-4 text-center text-lg font-semibold text-white">
                    Оплату успішно підтверджено
                </h2>

                <p class="mt-2 text-center text-sm text-gray-300">
                    @if($paymentOrderId)
                        Замовлення #{{ $paymentOrderId }} успішно оплачено.
                    @else
                        Ваш платіж успішно підтверджено.
                    @endif
                </p>

                <button
                    type="button"
                    wire:click="dismissPaymentSuccessModal"
                    class="mt-5 w-full rounded-xl bg-yellow-400 px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-yellow-500"
                >
                    Закрити
                </button>
            </div>
        </div>
    @elseif($paymentStatus === 'failed')
        <div class="mb-4 rounded-lg border border-red-400/40 bg-red-500/10 px-4 py-3 text-sm text-red-300">
            Платіж не був підтверджений. Спробуйте ще раз.
        </div>
    @endif

    {{-- TABS --}}
    <div class="flex gap-2 mb-6">
        <button
            wire:click="switchTab('active')"
            class="flex-1 px-4 py-1.5 rounded-lg text-sm text-center font-semibold
                {{ $tab === 'active'
                    ? 'bg-yellow-400 text-black'
                    : 'bg-gray-800 text-gray-400' }}"
        >
            Активні
        </button>

        <button
            wire:click="switchTab('history')"
            class="flex-1 px-4 py-1.5 rounded-lg text-sm text-center font-semibold
                {{ $tab === 'history'
                    ? 'bg-yellow-400 text-black'
                    : 'bg-gray-800 text-gray-400' }}"
        >
            Історія
        </button>
    </div>

    {{-- ================= ACTIVE ORDERS ================= --}}
    @if ($tab === 'active')

        <div class="space-y-4">

            @forelse($activeOrders as $order)

                @php
                    $isInProgress = $order->status === 'in_progress';
                    $isPayPending = $order->payment_status === \App\Models\Order::PAY_PENDING;
                @endphp

                <div
                    class="rounded-xl px-4 py-4 border transition
                    {{ $isInProgress
                        ? 'bg-gray-700/70 border-green-400/30 shadow-[0_0_0_1px_rgba(74,222,128,0.25)]'
                        : 'bg-gray-800 border-gray-700' }}">

                    {{-- STATUS + PRICE --}}
                    <div class="flex items-center justify-between mb-3">

                        {{-- STATUS --}}
                        <span class="inline-flex items-center px-3 py-1 rounded-full
                                     text-xs font-semibold
                            @if($order->status === 'searching')
                                bg-yellow-400 text-black
                            @elseif(in_array($order->status, ['found','accepted']))
                                bg-blue-500/90 text-white
                            @elseif($order->status === 'in_progress')
                                bg-green-400 text-black
                            @else
                                bg-gray-700 text-gray-300
                            @endif
                        ">
                            {{ $order->promiseStatusLabelForClient() }}
                        </span>

                        {{-- PRICE --}}
                        <span class="text-lg font-semibold text-yellow-400">
                            {{ $order->is_trial ? '0' : $order->price }} ₴
                        </span>
                    </div>

                    {{-- ADDRESS --}}
                    <div class="text-sm font-medium leading-snug">
                        {{ $order->address_text }}
                    </div>

                    {{-- DATE / TIME --}}
                    <div class="text-xs text-gray-400 mt-1">
                        Створено: {{ optional($order->created_at)->format('d.m.Y H:i') }}
                    </div>
                    <div class="text-xs text-gray-400 mt-1">
                        @if($order->service_mode === \App\Models\Order::SERVICE_MODE_ASAP)
                            Режим: Якнайшвидше
                        @else
                            Бажаний інтервал:
                            {{ optional($order->window_from_at)->format('d.m H:i') ?? $order->scheduled_time_from ?? '—' }}
                            – {{ optional($order->window_to_at)->format('d.m H:i') ?? $order->scheduled_time_to ?? '—' }}
                        @endif
                    </div>
                    <div class="text-xs text-gray-400 mt-1">
                        Активне до: {{ optional($order->valid_until_at)->format('d.m.Y H:i') ?? '—' }}
                    </div>

                    {{-- PAYMENT STATUS --}}
                    <div class="mt-2 text-xs font-medium
                        {{ $isPayPending ? 'text-yellow-400' : 'text-green-400' }}">
                        @if($order->payment_status === \App\Models\Order::PAY_PAID)
                            Замовлення #{{ $order->id }} оплачено!
                        @else
                            Замовлення #{{ $order->id }} · {{ \App\Models\Order::PAYMENT_LABELS[$order->payment_status] }}
                        @endif
                    </div>

                    {{-- CTA --}}
                    @if($isPayPending || $order->canBeCancelled())
                        <div class="flex gap-2 mt-4">
                            @if($isPayPending)
                                {{-- PAY --}}
                                <a href="{{ route('client.payments.show', $order) }}"
                                   class="flex-1 text-center
                                          bg-yellow-400 hover:bg-yellow-500
                                          text-black font-semibold
                                          py-2 rounded-lg transition">
                                    Оплатити {{ $order->price }} ₴
                                </a>
                            @endif

                            @if($order->canBeCancelled())
                                {{-- CANCEL --}}
                            <button
                                type="button"
                                wire:click="cancelOrder({{ $order->id }})"
                                wire:loading.attr="disabled"
                                wire:target="cancelOrder({{ $order->id }})"
                                class="px-4 py-2 rounded-lg
                                       border border-gray-600
                                       text-gray-300 text-sm
                                       hover:bg-gray-700 transition">
                                Скасувати
                            </button>
                            @endif
                        </div>
                    @endif

                </div>

            @empty
                <div class="text-gray-400 text-sm mt-10 text-center">
                    Активних замовлень немає
                </div>
            @endforelse

        </div>

    @endif


    {{-- ================= HISTORY ORDERS ================= --}}
    @if ($tab === 'history')

        <div class="space-y-4">

            @forelse($historyOrders as $order)

                @php $isCancelled = $order->status === \App\Models\Order::STATUS_CANCELLED; @endphp

                <div class="rounded-xl px-4 py-4 border
                    {{ $isCancelled
                        ? 'bg-red-950/40 border-red-500/40'
                        : 'bg-gray-800 border-gray-700' }}">

                    {{-- STATUS + PRICE --}}
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold
                            {{ $isCancelled ? 'text-red-300' : 'text-gray-300' }}">
                            {{ $order->promiseStatusLabelForClient() }}
                        </span>

                        <span class="text-sm text-yellow-400 font-semibold">
                            {{ $order->price }} ₴
                        </span>
                    </div>

                    {{-- ADDRESS --}}
                    <div class="text-sm font-medium">
                        {{ $order->address_text }}
                    </div>

                    {{-- DATE --}}
                    <div class="text-xs text-gray-400 mt-1">
                        {{ optional($order->created_at)->format('d.m.Y H:i') }}
                    </div>

                    @if($isCancelled && $order->expired_at)
                        <div class="text-xs text-red-300/90 mt-1">
                            {{ $order->expiredReasonLabelForClient() ?? 'Скасовано системою через неактуальність.' }}
                        </div>
                    @endif

                    {{-- CTA --}}
                    <button
                        wire:click="repeatOrder({{ $order->id }})"
                        class="mt-3 w-full bg-neutral-700 hover:bg-neutral-600
                               text-sm py-2 rounded-lg transition">
                        🔁 Замовити знову
                    </button>

                </div>

            @empty
                <div class="text-gray-400 text-sm mt-10 text-center">
                    Історія замовлень порожня
                </div>
            @endforelse

        </div>

    @endif

</div>
