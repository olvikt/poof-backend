<div class="relative w-full pb-28 text-white" wire:poll.5s>

    <div class="mb-5 mt-2 flex items-end justify-between gap-3 px-1">
        <div>
            <div class="text-[10px] uppercase tracking-[0.2em] text-slate-500">Courier workspace</div>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-100">Мої замовлення</h2>
        </div>

        <div class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1.5 text-[11px] text-slate-300">
            {{ $orders->count() }} активних
        </div>
    </div>

    @if($orders->isEmpty())
        <div class="rounded-3xl border border-white/10 bg-[#101722] p-8 text-center shadow-[0_16px_36px_rgba(0,0,0,0.35)]">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/5 text-slate-300">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 7h18" />
                    <path d="M5 7v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7" />
                    <path d="m9 11 3 3 3-3" />
                </svg>
            </div>
            <div class="text-lg font-semibold text-slate-100">Активних замовлень немає</div>
            <div class="mt-2 text-sm text-slate-400">Прийміть замовлення на головному екрані, щоб почати роботу</div>
        </div>
    @else

        <div class="relative mb-5 h-80 w-full overflow-hidden rounded-3xl border border-white/10 shadow-[0_16px_40px_rgba(0,0,0,0.4)]" data-map-bootstrap='@json($mapBootstrap ?? null)'>
            <div wire:ignore id="map" class="absolute inset-0"></div>
            <div class="pointer-events-none absolute inset-x-0 top-0 h-16 bg-gradient-to-b from-black/45 to-transparent"></div>
            <div class="absolute left-3 top-3 z-10 rounded-full border border-white/15 bg-[#0f1621]/85 px-3 py-1.5 text-[11px] font-medium text-slate-200 backdrop-blur-sm">
                Маршрут і локація
            </div>
        </div>

        <div class="space-y-4">
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
                @endphp

                <div wire:key="my-order-{{ $order->id }}" class="overflow-hidden rounded-3xl border border-white/10 bg-[#101722] shadow-[0_18px_40px_rgba(0,0,0,0.35)]">

                    <div class="border-b border-white/10 p-4">
                        <div class="flex gap-2">
                            <button
                                type="button"
                                wire:click="navigate({{ $order->id }})"
                                @if(! $online) disabled @endif
                                class="flex h-11 flex-1 items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/5 text-sm font-semibold transition active:scale-[0.99] hover:bg-white/10 disabled:pointer-events-none disabled:opacity-40"
                            >
                                <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4.4 8-10a8 8 0 1 0-16 0c0 5.6 8 10 8 10Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <span>Навігація</span>
                            </button>

                            <a
                                href="{{ $clientPhone ? 'tel:' . $clientPhone : '#' }}"
                                @if(! $clientPhone) aria-disabled="true" @endif
                                class="flex h-11 flex-1 items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/5 text-sm font-semibold transition active:scale-[0.99] hover:bg-white/10 {{ ($online && $clientPhone) ? '' : 'pointer-events-none opacity-40' }}"
                            >
                                <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .8 2.9a2 2 0 0 1-.5 2.1L8 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.4 1.9.7 2.9.8A2 2 0 0 1 22 16.9z"/></svg>
                                <span>Зв’язок</span>
                            </a>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px]">
                            <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-slate-300">#{{ $order->id }}</span>

                            @if($distanceKm !== null)
                                <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 {{ $distanceKm <= 1 ? 'text-emerald-300' : ($distanceKm <= 3 ? 'text-amber-300' : 'text-orange-300') }}">
                                    {{ number_format($distanceKm, 1) }} км
                                </span>
                            @endif

                            @if($etaMin !== null)
                                <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-slate-200">~{{ $etaMin }} хв</span>
                            @endif

                            @if($elapsedLabel && $order->status === \App\Models\Order::STATUS_IN_PROGRESS)
                                <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-emerald-300">{{ $elapsedLabel }}</span>
                            @elseif($elapsedLabel && $order->status === \App\Models\Order::STATUS_ACCEPTED)
                                <span class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-amber-300">{{ $elapsedLabel }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-[11px] uppercase tracking-[0.16em] text-slate-500">Замовлення</div>
                                <div class="mt-1 truncate text-2xl font-bold">#{{ $order->id }}</div>
                            </div>

                            <span
                                class="shrink-0 rounded-full px-3 py-1.5 text-[11px] font-semibold
                                @if($order->status === \App\Models\Order::STATUS_ACCEPTED)
                                    border border-amber-300/40 bg-amber-400/20 text-amber-200
                                @elseif($order->status === \App\Models\Order::STATUS_IN_PROGRESS)
                                    border border-sky-300/40 bg-sky-500/20 text-sky-100
                                @elseif($order->status === \App\Models\Order::STATUS_COMPLETED)
                                    border border-emerald-300/40 bg-emerald-500/20 text-emerald-200
                                @else
                                    border border-white/20 bg-white/10 text-slate-100
                                @endif
                                "
                            >
                                {{ \App\Models\Order::STATUS_LABELS[$order->status] ?? $order->status }}
                            </span>
                        </div>

                        <div class="space-y-4 text-sm">
                            <div class="rounded-2xl border border-white/10 bg-[#0c131d] p-3.5">
                                <div class="text-[11px] uppercase tracking-[0.16em] text-slate-500">Адреса</div>
                                <div class="mt-1.5 font-semibold leading-snug text-slate-100">{{ $order->address_text ?? 'Адреса не вказана' }}</div>
                                <div class="mt-2 text-xs text-slate-400">
                                    {{ $order->scheduled_time_from ?? '—' }} – {{ $order->scheduled_time_to ?? '—' }}
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs text-slate-300">
                                <div class="rounded-2xl border border-white/10 bg-[#0c131d] p-3"><div class="text-slate-500">Під’їзд</div><div class="mt-1 font-semibold">{{ $order->entrance ?? '—' }}</div></div>
                                <div class="rounded-2xl border border-white/10 bg-[#0c131d] p-3"><div class="text-slate-500">Поверх</div><div class="mt-1 font-semibold">{{ $order->floor ?? '—' }}</div></div>
                                <div class="rounded-2xl border border-white/10 bg-[#0c131d] p-3"><div class="text-slate-500">Квартира</div><div class="mt-1 font-semibold">{{ $order->apartment ?? '—' }}</div></div>
                                <div class="rounded-2xl border border-white/10 bg-[#0c131d] p-3"><div class="text-slate-500">Код</div><div class="mt-1 font-semibold">{{ $order->intercom_code ?? '—' }}</div></div>
                            </div>

                            <div class="flex items-center justify-between border-t border-white/10 pt-4">
                                <div class="text-[11px] uppercase tracking-[0.16em] text-slate-500">Вартість</div>
                                <div class="text-2xl font-bold text-amber-300">{{ number_format((float)($order->price ?? 0), 0, '.', ' ') }} ₴</div>
                            </div>

                            @if(($order->delivery_type ?? null) === 'door')
                                <div class="inline-flex rounded-full border border-amber-300/40 bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-200">Залишити біля дверей</div>
                            @else
                                <div class="inline-flex rounded-full border border-emerald-300/40 bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold text-emerald-200">Передати в руки</div>
                            @endif

                            @if(!empty($order->comment))
                                <div class="rounded-2xl border border-white/10 bg-[#0c131d] p-3 text-sm text-slate-200">
                                    <div class="mb-1 text-[11px] uppercase tracking-[0.16em] text-slate-500">Коментар</div>
                                    <div class="leading-snug break-words">{{ $order->comment }}</div>
                                </div>
                            @endif
                        </div>

                        <div class="mt-6">
                            @if($order->status === \App\Models\Order::STATUS_ACCEPTED)
                                <button
                                    type="button"
                                    wire:click="start({{ $order->id }})"
                                    wire:loading.attr="disabled"
                                    @if(! $online) disabled @endif
                                    class="h-13 w-full rounded-2xl bg-amber-300 text-base font-bold text-[#191204] shadow-lg transition active:scale-[0.99] disabled:pointer-events-none disabled:opacity-40"
                                >
                                    Почати виконання
                                </button>
                            @elseif($order->status === \App\Models\Order::STATUS_IN_PROGRESS)
                                <button
                                    type="button"
                                    wire:click="complete({{ $order->id }})"
                                    wire:loading.attr="disabled"
                                    @if(! $online) disabled @endif
                                    class="h-13 w-full rounded-2xl bg-emerald-400 text-base font-bold text-[#07160e] shadow-lg transition active:scale-[0.99] disabled:pointer-events-none disabled:opacity-40"
                                >
                                    Завершити замовлення
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    @endif
</div>
