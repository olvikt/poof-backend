<div class="w-full px-4 py-4 text-white" style="padding-bottom: calc(var(--courier-nav-h) + env(safe-area-inset-bottom) + 7.5rem);" wire:poll.5s>
    <div class="sr-only" aria-hidden="true" data-map-bootstrap='@json($mapBootstrap ?? null)'></div>

    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-slate-100">Мої замовлення</h2>
        <span class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs text-slate-300">{{ $orders->count() }} активних</span>
    </div>

    @if($orders->isEmpty())
        <div class="courier-surface border border-white/10 p-6">
            <div class="text-base font-semibold text-slate-100">Активних замовлень немає</div>
            <div class="mt-2 text-sm text-slate-400">Поверніться на головний екран і прийміть замовлення з мапи.</div>
            <a
                href="{{ route('courier.orders') }}"
                wire:navigate
                class="courier-btn courier-btn-primary mt-4"
            >
                До доступних замовлень
            </a>
        </div>
    @else
        @php
            $activeOrderForMap = $orders->firstWhere('status', \App\Models\Order::STATUS_IN_PROGRESS)
                ?? $orders->firstWhere('status', \App\Models\Order::STATUS_ACCEPTED)
                ?? $orders->first();
            $hasMapPreviewData = (bool) (
                isset($mapBootstrap['orderLat'], $mapBootstrap['orderLng'])
                || isset($mapBootstrap['courierLat'], $mapBootstrap['courierLng'])
            );

            $primaryActionOrder = $orders->firstWhere('status', \App\Models\Order::STATUS_IN_PROGRESS)
                ?? $orders->firstWhere('status', \App\Models\Order::STATUS_ACCEPTED);
        @endphp

        <div class="mb-4 overflow-hidden rounded-3xl border border-white/[0.08] bg-[#0d141e] shadow-[0_16px_36px_rgba(0,0,0,0.35)]">
            <div class="relative h-[50dvh] min-h-[360px] max-h-[560px] w-full overflow-hidden bg-[#0b131d]" data-map-bootstrap='@json($mapBootstrap ?? null)'>
                <div wire:ignore id="my-orders-map" class="absolute inset-0" data-map-bootstrap='@json($mapBootstrap ?? null)'></div>
                <div class="pointer-events-none absolute inset-x-0 top-0 h-16 bg-gradient-to-b from-[#0d141e]/[0.75] to-transparent"></div>

                @if($activeOrderForMap)
                    <div class="absolute bottom-3 right-3 z-20">
                        <button
                            type="button"
                            wire:click="navigate({{ $activeOrderForMap->id }})"
                            @if(! $online) disabled @endif
                            class="courier-btn courier-btn-warning h-11 min-w-[132px] rounded-full px-4 text-xs"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 3 3 10l7 2 2 7 9-16Z"/></svg>
                            Навігація
                        </button>
                    </div>
                @endif

                @unless($hasMapPreviewData)
                    <div class="absolute inset-0 flex items-center justify-center px-4">
                        <div class="rounded-2xl border border-white/[0.12] bg-[#101722]/[0.95] px-3 py-2 text-center text-xs text-slate-200">
                            Карта маршруту з’явиться, щойно буде доступна геопозиція курʼєра.
                        </div>
                    </div>
                @endunless
            </div>
        </div>

        <div class="space-y-3">
            @foreach($orders as $order)
                @php
                    $timerStart = $order->started_at ?? $order->accepted_at ?? null;

                    $elapsedLabel = null;
                    if ($timerStart) {
                        try {
                            $diff = now()->diff(\Carbon\Carbon::parse($timerStart));
                            $hours = (int) $diff->format('%r%h');
                            $mins  = (int) $diff->format('%r%i');
                            $secs  = (int) $diff->format('%r%s');
                            $elapsedLabel = $hours > 0
                                ? sprintf('%02d:%02d:%02d', $hours, $mins, $secs)
                                : sprintf('%02d:%02d', $mins, $secs);
                        } catch (\Throwable $e) {
                            $elapsedLabel = null;
                        }
                    }

                    $distanceKm = (isset($order->distance_km) && $order->distance_km !== null)
                        ? (float) $order->distance_km
                        : null;

                    $etaMin = (isset($order->eta_minutes) && $order->eta_minutes)
                        ? (int) $order->eta_minutes
                        : null;

                    $clientPhone = $order->client?->phone ?? null;

                    $addressDetails = [
                        'Підʼїзд' => $order->entrance,
                        'Поверх' => $order->floor,
                        'Квартира' => $order->apartment,
                        'Домофон' => $order->intercom,
                    ];
                @endphp

                <div wire:key="my-order-{{ $order->id }}" class="courier-surface border border-white/[0.08] p-4" x-data="{ contactOpen: false, clientPhone: @js($clientPhone) }">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-slate-200">Замовлення #{{ $order->id }}</div>
                            <div class="mt-1 flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1 pr-1 text-xs leading-relaxed text-slate-300">{{ $order->address_text ?? 'Адреса не вказана' }}</div>
                                <div class="flex shrink-0 flex-wrap justify-end gap-1.5 text-[11px]">
                                    @if($distanceKm !== null)
                                        <span class="rounded-full border border-white/[0.12] bg-white/[0.08] px-2 py-0.5 {{ $distanceKm <= 1 ? 'text-emerald-300' : ($distanceKm <= 3 ? 'text-amber-300' : 'text-orange-300') }}">{{ number_format($distanceKm, 1) }} км</span>
                                    @endif

                                    @if($etaMin !== null)
                                        <span class="rounded-full border border-white/[0.12] bg-white/[0.08] px-2 py-0.5 text-slate-200">~{{ $etaMin }} хв</span>
                                    @endif

                                    @if($elapsedLabel)
                                        <span class="rounded-full border border-white/[0.12] bg-white/[0.08] px-2 py-0.5 text-slate-200">{{ $elapsedLabel }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 rounded-2xl border border-white/[0.06] bg-[#0d1522] p-3">
                        <div class="mb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-400">Доступ до клієнта</div>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($addressDetails as $label => $value)
                                <div class="rounded-xl bg-white/[0.04] px-2.5 py-2">
                                    <div class="text-[10px] uppercase tracking-[0.07em] text-slate-500">{{ $label }}</div>
                                    <div class="mt-0.5 text-sm font-semibold text-slate-100">{{ filled($value) ? $value : '—' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-3 flex items-center gap-2.5">
                        <button
                            type="button"
                            @click="contactOpen = true"
                            @if(! ($online && $clientPhone)) disabled @endif
                            class="courier-btn courier-btn-success h-10 rounded-xl px-3 text-xs"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.89.33 1.76.63 2.6a2 2 0 0 1-.45 2.11L8 9.91a16 16 0 0 0 6.09 6.09l1.48-1.29a2 2 0 0 1 2.11-.45c.84.3 1.71.51 2.6.63A2 2 0 0 1 22 16.92Z"/></svg>
                            Звʼязок
                        </button>
                    </div>

                    <div
                        x-show="contactOpen"
                        x-cloak
                        x-transition.opacity
                        @click="contactOpen = false"
                        class="fixed inset-0 z-[70] bg-black/65"
                    >
                        <div
                            @click.stop
                            class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-md rounded-t-3xl bg-[#0c131d] p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-[0_-18px_42px_rgba(0,0,0,0.7)]"
                        >
                            <div class="mx-auto mb-3 h-1.5 w-11 rounded-full bg-white/30"></div>
                            <div class="text-sm font-semibold text-slate-100">Зв’язок із клієнтом</div>
                            <div class="mt-3 space-y-2">
                                <a
                                    href="{{ $clientPhone ? 'tel:' . $clientPhone : '#' }}"
                                    @if(! $clientPhone) aria-disabled="true" @endif
                                    class="courier-btn courier-btn-primary w-full"
                                >
                                    Зателефонувати
                                </a>
                                @if($clientPhone)
                                    <button
                                        type="button"
                                        class="courier-btn courier-btn-secondary w-full"
                                        @click="if (clientPhone) { navigator.clipboard?.writeText(clientPhone) }; contactOpen = false"
                                    >
                                        Скопіювати номер
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($primaryActionOrder)
            <div class="fixed bottom-[calc(var(--courier-nav-h)+env(safe-area-inset-bottom)+var(--courier-screen-bottom-gap))] left-1/2 z-40 w-full max-w-md -translate-x-1/2 px-4">
                <div class="rounded-2xl border border-white/10 bg-[#0f1724]/[0.95] p-2 shadow-[0_-10px_28px_rgba(0,0,0,0.45)] backdrop-blur-sm">
                    @if($primaryActionOrder->status === \App\Models\Order::STATUS_ACCEPTED)
                        <button
                            type="button"
                            wire:click="start({{ $primaryActionOrder->id }})"
                            wire:loading.attr="disabled"
                            @if(! $online) disabled @endif
                            class="courier-btn courier-btn-warning h-12 w-full"
                        >
                            Почати виконання · #{{ $primaryActionOrder->id }}
                        </button>
                    @elseif($primaryActionOrder->status === \App\Models\Order::STATUS_IN_PROGRESS)
                        <button
                            type="button"
                            wire:click="complete({{ $primaryActionOrder->id }})"
                            wire:loading.attr="disabled"
                            @if(! $online) disabled @endif
                            class="courier-btn courier-btn-success h-12 w-full"
                        >
                            Завершити замовлення · #{{ $primaryActionOrder->id }}
                        </button>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>
