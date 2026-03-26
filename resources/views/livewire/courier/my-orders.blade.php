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
        @endphp

        <div class="mb-4 overflow-hidden rounded-3xl border border-white/10 bg-[#0d141e] shadow-[0_16px_36px_rgba(0,0,0,0.35)]">
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.08em] text-slate-400">Маршрут</div>
                    <div class="text-sm font-semibold text-slate-100">
                        @if($activeOrderForMap)
                            До замовлення #{{ $activeOrderForMap->id }}
                        @else
                            Активний маршрут
                        @endif
                    </div>
                </div>
                @if($activeOrderForMap)
                    <button
                        type="button"
                        wire:click="navigate({{ $activeOrderForMap->id }})"
                        @if(! $online) disabled @endif
                        class="rounded-xl border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold text-slate-100 transition hover:bg-white/15 disabled:pointer-events-none disabled:opacity-40"
                    >
                        Навігація
                    </button>
                @endif
            </div>
            <div class="relative h-36 w-full overflow-hidden bg-[#0a111a]" data-map-bootstrap='@json($mapBootstrap ?? null)'>
                <div wire:ignore id="my-orders-map" class="absolute inset-0" data-map-bootstrap='@json($mapBootstrap ?? null)'></div>
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#0d141e] via-transparent to-transparent"></div>
                @if($activeOrderForMap)
                    <div class="absolute inset-x-3 bottom-3 rounded-2xl border border-white/10 bg-[#101722]/90 px-3 py-2 text-xs text-slate-200">
                        {{ $activeOrderForMap->address_text ?? 'Адреса не вказана' }}
                    </div>
                @endif
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
                @endphp

                <div wire:key="my-order-{{ $order->id }}" class="rounded-3xl border border-white/10 bg-[#111926] p-4 shadow-[0_18px_36px_rgba(0,0,0,0.32)]">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-lg font-bold text-white">#{{ $order->id }}</div>
                            <div class="mt-1 text-xs text-slate-400">{{ $order->address_text ?? 'Адреса не вказана' }}</div>
                        </div>

                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold
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

                    <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px]">
                        @if($distanceKm !== null)
                            <span class="rounded-full border border-white/15 bg-white/10 px-2.5 py-1 {{ $distanceKm <= 1 ? 'text-emerald-300' : ($distanceKm <= 3 ? 'text-amber-300' : 'text-orange-300') }}">{{ number_format($distanceKm, 1) }} км</span>
                        @endif

                        @if($etaMin !== null)
                            <span class="rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-slate-200">~{{ $etaMin }} хв</span>
                        @endif

                        @if($elapsedLabel)
                            <span class="rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-slate-200">{{ $elapsedLabel }}</span>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            wire:click="navigate({{ $order->id }})"
                            @if(! $online) disabled @endif
                            class="flex h-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-sm font-semibold transition hover:bg-white/15 disabled:pointer-events-none disabled:opacity-40"
                        >
                            Навігація
                        </button>

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
