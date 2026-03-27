<div class="w-full px-4 py-4 pb-28 text-white" wire:poll.5s>
    <div class="sr-only" aria-hidden="true" data-map-bootstrap='@json($mapBootstrap ?? null)'></div>

    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-slate-100">Мої замовлення</h2>
        <span class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs text-slate-300">{{ $orders->count() }} активних</span>
    </div>

    @if($orders->isEmpty())
        <div class="rounded-3xl border border-white/10 bg-[#101722] p-6 shadow-[0_16px_36px_rgba(0,0,0,0.35)]">
            <div class="text-base font-semibold text-slate-100">Активних замовлень немає</div>
            <div class="mt-2 text-sm text-slate-400">Поверніться на головний екран і прийміть замовлення з мапи.</div>
            <a
                href="{{ route('courier.orders') }}"
                wire:navigate
                class="mt-4 inline-flex h-11 items-center justify-center rounded-2xl bg-poof px-4 text-sm font-semibold text-[#051014] transition hover:bg-poof/90"
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
        @endphp

        <div class="mb-4 overflow-hidden rounded-3xl border border-white/10 bg-[#0d141e] shadow-[0_16px_36px_rgba(0,0,0,0.35)]">
            @if($activeOrderForMap)
                <div class="border-b border-white/10 px-4 py-2.5">
                    <button
                        type="button"
                        wire:click="navigate({{ $activeOrderForMap->id }})"
                        @if(! $online) disabled @endif
                        class="inline-flex h-9 items-center justify-center rounded-xl bg-amber-300 px-3.5 text-xs font-semibold text-[#1d1508] shadow-[0_10px_24px_rgba(252,211,77,0.25)] transition hover:bg-amber-200 disabled:pointer-events-none disabled:opacity-40"
                    >
                        Навігація
                    </button>
                </div>
            @endif
            <div class="relative h-[45dvh] min-h-[320px] max-h-[520px] w-full overflow-hidden bg-[#0b131d]" data-map-bootstrap='@json($mapBootstrap ?? null)'>
                <div wire:ignore id="my-orders-map" class="absolute inset-0" data-map-bootstrap='@json($mapBootstrap ?? null)'></div>
                <div class="pointer-events-none absolute inset-x-0 top-0 h-16 bg-gradient-to-b from-[#0d141e]/75 to-transparent"></div>
                @unless($hasMapPreviewData)
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="rounded-2xl border border-white/15 bg-[#101722]/95 px-3 py-2 text-center text-xs text-slate-200">
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
                    $addressDetailRows = array_filter([
                        'Дім' => data_get($order, 'house'),
                        'Підʼїзд' => $order->entrance,
                        'Поверх' => $order->floor,
                        'Квартира' => $order->apartment,
                        'Домофон' => $order->intercom,
                    ], static fn ($value) => filled($value));
                @endphp

                <div wire:key="my-order-{{ $order->id }}" class="rounded-3xl border border-white/10 bg-[#111926] p-4 shadow-[0_18px_36px_rgba(0,0,0,0.32)]">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-slate-200">Замовлення #{{ $order->id }}</div>
                            <div class="mt-1 flex items-start justify-between gap-2">
                                <div class="min-w-0 text-xs text-slate-400">{{ $order->address_text ?? 'Адреса не вказана' }}</div>
                                <div class="flex shrink-0 items-center gap-1.5 text-[11px]">
                                    @if($distanceKm !== null)
                                        <span class="rounded-full border border-white/15 bg-white/10 px-2 py-0.5 {{ $distanceKm <= 1 ? 'text-emerald-300' : ($distanceKm <= 3 ? 'text-amber-300' : 'text-orange-300') }}">{{ number_format($distanceKm, 1) }} км</span>
                                    @endif

                                    @if($etaMin !== null)
                                        <span class="rounded-full border border-white/15 bg-white/10 px-2 py-0.5 text-slate-200">~{{ $etaMin }} хв</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px]">
                        @if($elapsedLabel)
                            <span class="rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-slate-200">{{ $elapsedLabel }}</span>
                        @endif
                    </div>

                    @if($addressDetailRows !== [])
                        <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-[11px] text-slate-300">
                            @foreach($addressDetailRows as $label => $value)
                                <div class="flex items-center gap-1.5">
                                    <span class="text-slate-500">{{ $label }}:</span>
                                    <span class="font-medium text-slate-200">{{ $value }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-4">
                        <a
                            href="{{ $clientPhone ? 'tel:' . $clientPhone : '#' }}"
                            @if(! $clientPhone) aria-disabled="true" @endif
                            class="flex h-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-sm font-semibold transition hover:bg-white/15 {{ ($online && $clientPhone) ? '' : 'pointer-events-none opacity-40' }}"
                        >
                            Зв’язок
                        </a>
                    </div>

                    <div class="mt-4 border-t border-white/10 pt-4">
                        @if($order->status === \App\Models\Order::STATUS_ACCEPTED)
                            <button
                                type="button"
                                wire:click="start({{ $order->id }})"
                                wire:loading.attr="disabled"
                                @if(! $online) disabled @endif
                                class="h-12 w-full rounded-2xl bg-amber-300 text-sm font-bold text-[#191204] shadow-lg transition active:scale-[0.99] disabled:pointer-events-none disabled:opacity-40"
                            >
                                Почати виконання
                            </button>
                        @elseif($order->status === \App\Models\Order::STATUS_IN_PROGRESS)
                            <button
                                type="button"
                                wire:click="complete({{ $order->id }})"
                                wire:loading.attr="disabled"
                                @if(! $online) disabled @endif
                                class="h-12 w-full rounded-2xl bg-emerald-400 text-sm font-bold text-[#07160e] shadow-lg transition active:scale-[0.99] disabled:pointer-events-none disabled:opacity-40"
                            >
                                Завершити замовлення
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
