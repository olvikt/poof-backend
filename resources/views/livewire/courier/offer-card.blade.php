<div wire:poll.{{ $pollIntervalSeconds }}s="loadOffer">

    @if ($offer)
        @php
            $order      = $offer->order;
            $isStack    = $offer->isStack();
            $distance   = $this->distanceKm;
            $windowLabel = $order?->service_mode === \App\Models\Order::SERVICE_MODE_ASAP
                ? 'Якнайшвидше'
                : (($order?->window_from_at?->format('H:i') ?? $order?->scheduled_time_from ?? '—') . '–' . ($order?->window_to_at?->format('H:i') ?? $order?->scheduled_time_to ?? '—'));
            $warningMinutes = max(1, (int) config('order_promise.courier_urgency_warning_minutes', 30));
            $isUrgent = $order?->valid_until_at?->diffInMinutes(now(), false) !== null
                && $order?->valid_until_at?->isFuture()
                && $order?->valid_until_at?->diffInMinutes(now()) <= $warningMinutes;
        @endphp

        {{-- WRAPPER --}}
        <div class="fixed bottom-[calc(var(--courier-nav-h)+env(safe-area-inset-bottom)+0.2rem)] left-0 right-0 z-50 pointer-events-none">

            <div class="max-w-md mx-auto px-4 pointer-events-auto">

                <div class="
                    relative
                    rounded-3xl
                    bg-gradient-to-br from-gray-900 to-gray-800
                    border border-gray-700/70
                    shadow-2xl
                    overflow-hidden
                ">

                    {{-- TOP STRIP --}}
                    <div class="absolute top-0 left-0 right-0 h-1 bg-poof"></div>

                    <div class="p-5">
                      {{-- PRICE (главный акцент) --}}
                        <div class="mt-4 mb-4 text-center border-b border-gray-600">
                            <div class="text-4xl pb-4 font-black text-green-400 tracking-tight">
                                {{ $order?->price ?? '—' }} ₴
                            </div>
                        </div>
                        {{-- HEADER ROW --}}
                        <div class="flex items-start justify-between">
						
						

                            <div>

                                {{-- TYPE BADGE --}}
                                <div class="
                                    inline-flex items-center
                                    text-xs font-semibold
                                    px-2.5 py-1
                                    rounded-full
                                    {{ $isStack ? 'bg-purple-600/20 text-purple-400' : 'bg-green-600/20 text-green-400' }}
                                ">
                                    {{ $isStack ? '📦 Додаткове поруч' : '🆕 Нове замовлення' }}
                                </div>

                                {{-- ORDER ID --}}
                                <div class="mt-3 text-lg font-extrabold text-white">
                                    Замовлення #{{ $offer->order_id }}
                                </div>

                            </div>

                            {{-- EXPIRE TIMER --}}
                            <div class="text-right">
                                <div class="text-xs text-gray-400">
                                    до
                                </div>
                                <div class="text-sm font-semibold text-gray-200">
                                    {{ $offer->expires_at?->format('H:i:s') ?? '—' }}
                                </div>
                            </div>

                        </div>

                       

                        {{-- INFO BLOCK --}}
                        <div class="mt-5 space-y-2 text-sm">

                            {{-- TIME --}}
                            <div class="flex items-center justify-between text-gray-300">
                                <span>⏰ {{ $windowLabel }}</span>
                            </div>

                            <div class="flex items-center justify-between text-gray-300">
                                <span>🕓 Створено: {{ optional($order?->created_at)->format('d.m H:i') ?? '—' }}</span>
                            </div>

                            <div class="flex items-center justify-between text-gray-300">
                                <span>⌛ Активне до: {{ optional($order?->valid_until_at)->format('d.m H:i') ?? '—' }}</span>
                                @if($isUrgent)
                                    <span class="ml-2 rounded-full bg-amber-500/20 px-2 py-0.5 text-[11px] font-semibold text-amber-300">Терміново</span>
                                @endif
                            </div>

                            {{-- ADDRESS --}}
                            <div class="flex items-center justify-between text-gray-200">

                                <div class="truncate max-w-[70%]">
                                    📍 {{ $order?->address_text ?? '—' }}
                                </div>

                                @if ($distance)
                                    <div class="ml-3 text-xs px-2 py-1 rounded-lg bg-gray-700 text-gray-300">
                                       📏 {{ $this->distanceKm }} км до точки
                                    </div>
                                @endif

                            </div>

                        </div>

                        {{-- STACK INFO --}}
                        @if ($isStack)
                            <div class="mt-3 text-xs text-gray-400">
                                До активного: #{{ $offer->parent_order_id }}
                            </div>
                        @endif

                        {{-- ACTIONS --}}
                        <div class="mt-6 grid grid-cols-2 gap-3">

                            <button type="button" wire:click="reject" class="courier-btn courier-btn-secondary h-12 border-gray-600 bg-gray-800 text-gray-200 hover:bg-gray-700">
                                Пропустити
                            </button>

                            <button type="button" wire:click="accept" data-e2e="courier-accept-offer" class="courier-btn courier-btn-warning h-12 font-bold">
                                Прийняти
                            </button>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    @endif

</div>
