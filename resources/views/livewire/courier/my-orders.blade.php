<div class="relative w-full px-2 sm:px-3 pb-28 text-white" wire:poll.5s>

    {{-- HEADER --}}
    <div class="flex items-end justify-between mb-4 mt-3">
        <div>
            <div class="text-xs text-gray-500 uppercase tracking-wide">
                Курʼєр
            </div>
            <h2 class="text-2xl font-extrabold tracking-tight leading-tight">
                🚴‍♂️ Мої замовлення
            </h2>
        </div>

        <div class="text-[11px] text-gray-400 bg-zinc-900/70 border border-zinc-800 px-2.5 py-1 rounded-full">
            {{ $orders->count() }} активних
        </div>
    </div>

    {{-- EMPTY STATE --}}
    @if($orders->isEmpty())

        <div class="bg-zinc-900 border border-zinc-800 rounded-3xl p-8 text-center shadow-xl">
            <div class="text-5xl mb-4">📭</div>
            <div class="text-lg font-semibold text-gray-200">
                Активних замовлень немає
            </div>
            <div class="text-sm text-gray-500 mt-2">
                Прийміть замовлення, щоб почати роботу
            </div>
        </div>

    @else

        {{-- MAP --}}
        <div class="relative h-80 w-full rounded-3xl overflow-hidden mb-5 border border-zinc-800 shadow-xl">
            <div wire:ignore id="map" class="absolute inset-0"></div>

            {{-- subtle top gradient for readability --}}
            <div class="pointer-events-none absolute inset-x-0 top-0 h-16 bg-gradient-to-b from-black/40 to-transparent"></div>

            {{-- MAP BADGE --}}
            <div class="absolute top-3 left-3 z-10">
                <div class="text-[11px] font-semibold px-3 py-1.5 rounded-full bg-zinc-900/80 border border-zinc-800 backdrop-blur">
                    🗺 Маршрут & локація
                </div>
            </div>
        </div>

        <div class="space-y-5">

            @foreach($orders as $order)

                @php
                    // ---- SAFE TIMER (won't break if fields absent) ----
                    $timerStart = $order->started_at ?? $order->accepted_at ?? null;

                    $elapsedLabel = null;
                    if ($timerStart) {
                        try {
                            $diff = now()->diff(\Carbon\Carbon::parse($timerStart));
                            // HH:MM:SS if >= 1h else MM:SS
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

                <div
                    wire:key="my-order-{{ $order->id }}"
                    class="bg-zinc-900 border border-zinc-800 rounded-3xl shadow-2xl overflow-hidden"
                >

                    {{-- TOP ACTION BAR (flush, full width, minimal paddings) --}}
                    <div class="p-4 pb-3 border-b border-zinc-800/80">
                        <div class="flex gap-2">

                            <button
                                type="button"
                                wire:click="navigate({{ $order->id }})"
                                @if(! $online) disabled @endif
                                class="
                                    flex-1
                                    h-12
                                    rounded-2xl
                                    bg-zinc-800
                                    border border-zinc-700
                                    font-semibold
                                    flex items-center justify-center gap-2
                                    transition
                                    active:scale-[0.98]
                                    hover:bg-zinc-700
                                    {{ $online ? '' : 'opacity-40 pointer-events-none' }}
                                "
                            >
                                🗺 <span>Навігація</span>
                            </button>

                            <a
                                href="{{ $clientPhone ? 'tel:' . $clientPhone : '#' }}"
                                @if(! $clientPhone) aria-disabled="true" @endif
                                class="
                                    flex-1
                                    h-12
                                    rounded-2xl
                                    bg-zinc-800
                                    border border-zinc-700
                                    font-semibold
                                    flex items-center justify-center gap-2
                                    transition
                                    active:scale-[0.98]
                                    hover:bg-zinc-700
                                    {{ ($online && $clientPhone) ? '' : 'opacity-40 pointer-events-none' }}
                                "
                            >
                                📞 <span>Зв’язок</span>
                            </a>

                        </div>

                        {{-- QUICK CHIPS (distance / eta / timer) --}}
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <div class="text-[11px] px-2.5 py-1 rounded-full bg-black/30 border border-zinc-800 text-gray-300">
                                #{{ $order->id }}
                            </div>

                            @if($distanceKm !== null)
                                <div class="text-[11px] px-2.5 py-1 rounded-full border border-zinc-800 bg-black/30">
                                    🚗
                                    <span class="
                                        font-semibold
                                        @if($distanceKm <= 1) text-emerald-400
                                        @elseif($distanceKm <= 3) text-yellow-400
                                        @else text-orange-400
                                        @endif
                                    ">
                                        {{ number_format($distanceKm, 1) }} км
                                    </span>
                                </div>
                            @endif

                            @if($etaMin !== null)
                                <div class="text-[11px] px-2.5 py-1 rounded-full border border-zinc-800 bg-black/30 text-gray-200">
                                    ⏱ ~{{ $etaMin }} хв
                                </div>
                            @endif

                            @if($elapsedLabel && $order->status === \App\Models\Order::STATUS_IN_PROGRESS)
                                <div class="text-[11px] px-2.5 py-1 rounded-full border border-emerald-900/40 bg-emerald-500/10 text-emerald-300">
                                    ⏳ {{ $elapsedLabel }}
                                </div>
                            @elseif($elapsedLabel && $order->status === \App\Models\Order::STATUS_ACCEPTED)
                                <div class="text-[11px] px-2.5 py-1 rounded-full border border-yellow-900/40 bg-yellow-400/10 text-yellow-300">
                                    🕒 {{ $elapsedLabel }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- CONTENT --}}
                    <div class="p-4">

                        {{-- ORDER HEADER + STATUS --}}
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div class="min-w-0">
                                <div class="text-xs text-gray-500 uppercase tracking-wide">
                                    Замовлення
                                </div>
                                <div class="text-2xl font-extrabold leading-tight truncate">
                                    #{{ $order->id }}
                                </div>
                            </div>

                            <span
                                class="shrink-0 text-[11px] px-3 py-1.5 rounded-full font-semibold
                                    @if($order->status === \App\Models\Order::STATUS_ACCEPTED)
                                        bg-yellow-400 text-black
                                    @elseif($order->status === \App\Models\Order::STATUS_IN_PROGRESS)
                                        bg-blue-500 text-white
                                    @elseif($order->status === \App\Models\Order::STATUS_COMPLETED)
                                        bg-emerald-500 text-black
                                    @else
                                        bg-gray-700 text-white
                                    @endif
                                "
                            >
                                {{ \App\Models\Order::STATUS_LABELS[$order->status] ?? $order->status }}
                            </span>
                        </div>

                        {{-- ADDRESS + LOGISTICS (safe) --}}
                        <div class="space-y-4 text-sm">

                            {{-- Address --}}
                            <div class="flex items-start gap-3">
                                <div class="text-xl shrink-0">📍</div>

                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-gray-100 leading-snug break-words">
                                        {{ $order->address_text ?? 'Адреса не вказана' }}
                                    </div>

                                    {{-- Schedule --}}
                                    <div class="mt-2 flex items-center gap-2 text-gray-400 text-sm">
                                        🕒
                                        <span>
                                            {{ $order->scheduled_time_from ?? '—' }}
                                            –
                                            {{ $order->scheduled_time_to ?? '—' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- PRICE --}}
                            <div class="flex items-center justify-between pt-4 border-t border-zinc-800">
                                <div class="text-gray-500 text-xs uppercase tracking-wide">
                                    Вартість
                                </div>
                                <div class="text-2xl font-extrabold text-yellow-400">
                                    {{ number_format((float)($order->price ?? 0), 0, '.', ' ') }} ₴
                                </div>
                            </div>

                        </div>

                        {{-- DETAILS / ACCESS --}}
                        <div class="mt-4 pt-4 border-t border-zinc-800 space-y-2 text-sm text-gray-400">

                            <div class="grid grid-cols-2 gap-2">
                                <div class="bg-black/20 border border-zinc-800 rounded-2xl p-3">
                                    <div class="text-xs text-gray-500">🏢 Під’їзд</div>
                                    <div class="font-semibold text-gray-200">{{ $order->entrance ?? '—' }}</div>
                                </div>

                                <div class="bg-black/20 border border-zinc-800 rounded-2xl p-3">
                                    <div class="text-xs text-gray-500">🏠 Поверх</div>
                                    <div class="font-semibold text-gray-200">{{ $order->floor ?? '—' }}</div>
                                </div>

                                <div class="bg-black/20 border border-zinc-800 rounded-2xl p-3">
                                    <div class="text-xs text-gray-500">🚪 Квартира</div>
                                    <div class="font-semibold text-gray-200">{{ $order->apartment ?? '—' }}</div>
                                </div>

                                <div class="bg-black/20 border border-zinc-800 rounded-2xl p-3">
                                    <div class="text-xs text-gray-500">🔐 Код</div>
                                    <div class="font-semibold text-gray-200">{{ $order->intercom_code ?? '—' }}</div>
                                </div>
                            </div>

                            {{-- Delivery type --}}
                            <div class="pt-1">
                                @if(($order->delivery_type ?? null) === 'door')
                                    <div class="inline-flex items-center gap-2 text-yellow-300 bg-yellow-400/10 border border-yellow-900/40 px-3 py-2 rounded-2xl">
                                        🚪 <span class="font-semibold">Залишити біля дверей</span>
                                    </div>
                                @else
                                    <div class="inline-flex items-center gap-2 text-emerald-300 bg-emerald-500/10 border border-emerald-900/40 px-3 py-2 rounded-2xl">
                                        🤝 <span class="font-semibold">Передати в руки</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Comment --}}
                            @if(!empty($order->comment))
                                <div class="mt-2 bg-black/20 border border-zinc-800 rounded-2xl p-3">
                                    <div class="text-xs text-gray-500 mb-1">💬 Коментар</div>
                                    <div class="text-gray-200 leading-snug break-words">
                                        {{ $order->comment }}
                                    </div>
                                </div>
                            @endif

                        </div>

                        {{-- MAIN ACTION --}}
                        <div class="mt-6">

                            @if($order->status === \App\Models\Order::STATUS_ACCEPTED)

                                <button
                                    type="button"
                                    wire:click="start({{ $order->id }})"
                                    wire:loading.attr="disabled"
                                    class="
                                        w-full
                                        h-14
                                        rounded-2xl
                                        font-extrabold
                                        bg-yellow-400
                                        text-black
                                        text-lg
                                        transition
                                        active:scale-[0.98]
                                        shadow-lg
                                        disabled:opacity-60
                                    "
                                >
                                    ▶️ Почати виконання
                                </button>

                            @elseif($order->status === \App\Models\Order::STATUS_IN_PROGRESS)

                                <button
                                    type="button"
                                    wire:click="complete({{ $order->id }})"
                                    wire:loading.attr="disabled"
                                    class="
                                        w-full
                                        h-14
                                        rounded-2xl
                                        font-extrabold
                                        bg-emerald-500
                                        text-black
                                        text-lg
                                        transition
                                        active:scale-[0.98]
                                        shadow-lg
                                        disabled:opacity-60
                                    "
                                >
                                    ✅ Завершити замовлення
                                </button>

                            @endif

                        </div>

                    </div>

                </div>

            @endforeach

        </div>

    @endif


    @if(! $online && $orders->isEmpty())
        <div class="absolute inset-0 z-40 flex items-center justify-center rounded-2xl bg-black/70 backdrop-blur">
            <div class="bg-gray-900 border border-gray-700 rounded-2xl p-6 text-center shadow-xl">
                <div class="text-3xl mb-2">🛑</div>
                <div class="font-semibold text-white">Ви не на лінії</div>
                <div class="text-sm text-gray-400 mt-1">Дії з замовленнями тимчасово недоступні</div>
            </div>
        </div>
    @endif

</div>