<div class="w-full px-4 py-4 text-white" style="padding-bottom: calc(var(--courier-nav-h) + env(safe-area-inset-bottom) + 7.5rem);" wire:poll.{{ $pollIntervalSeconds }}s>
    <div class="sr-only" aria-hidden="true" data-map-bootstrap='@json($mapBootstrap ?? null)'></div>

    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-slate-100">Мої</h2>
        <span class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs text-slate-300">{{ $orders->count() }} активних</span>
    </div>

    <div class="mb-4 rounded-2xl border border-white/[0.08] bg-[#0d1522] p-1.5">
        <div class="grid grid-cols-2 gap-1.5">
            <button
                type="button"
                wire:click="setActiveTab('orders')"
                class="rounded-xl px-3 py-2 text-sm font-semibold transition {{ $activeTab === 'orders' ? 'bg-amber-400 text-[#101720]' : 'bg-transparent text-slate-300' }}"
            >
                Мої замовлення
            </button>
            <button
                type="button"
                wire:click="setActiveTab('stats')"
                class="rounded-xl px-3 py-2 text-sm font-semibold transition {{ $activeTab === 'stats' ? 'bg-amber-400 text-[#101720]' : 'bg-transparent text-slate-300' }}"
            >
                Статистика
            </button>
        </div>
    </div>

    @if($activeTab === 'orders')
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
                    $serviceModeLabel = match ($order->service_mode) {
                        \App\Models\Order::SERVICE_MODE_ASAP => 'Якнайшвидше',
                        \App\Models\Order::SERVICE_MODE_PREFERRED_WINDOW => 'Бажане вікно',
                        default => 'Інший режим',
                    };
                    $executionDateLabel = optional($order->window_from_at ?? $order->scheduled_date)->format('d.m.Y')
                        ?? optional($order->created_at)->format('d.m.Y')
                        ?? '—';
                    $desiredWindowLabel = null;
                    if ($order->service_mode === \App\Models\Order::SERVICE_MODE_PREFERRED_WINDOW) {
                        $desiredWindowLabel = ($order->window_from_at?->format('H:i') ?? $order->scheduled_time_from ?? '—')
                            .'–'
                            .($order->window_to_at?->format('H:i') ?? $order->scheduled_time_to ?? '—');
                    }
                    $validUntilLabel = optional($order->valid_until_at)->format('d.m H:i');
                    $warningMinutes = max(1, (int) config('order_promise.courier_urgency_warning_minutes', 30));
                    $isUrgent = $order->valid_until_at?->isFuture()
                        && $order->valid_until_at?->diffInMinutes(now()) <= $warningMinutes;

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
                    $completionRequest = $order->completionRequest;
                    $proofsByType = $completionRequest?->proofs?->keyBy('proof_type') ?? collect();
                    $uploadedProofTypes = $completionRequest?->proofs?->pluck('proof_type')->all() ?? [];
                    $needsProofFlow = $order->completion_policy === \App\Models\Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM
                        && $order->handover_type === \App\Models\Order::HANDOVER_DOOR;
                    $hasDoorProof = in_array(\App\Models\OrderCompletionProof::TYPE_DOOR_PHOTO, $uploadedProofTypes, true);
                    $hasContainerProof = in_array(\App\Models\OrderCompletionProof::TYPE_CONTAINER_PHOTO, $uploadedProofTypes, true);
                    $proofReadyToSubmit = $hasDoorProof && $hasContainerProof;
                    $doorProof = $proofsByType->get(\App\Models\OrderCompletionProof::TYPE_DOOR_PHOTO);
                    $containerProof = $proofsByType->get(\App\Models\OrderCompletionProof::TYPE_CONTAINER_PHOTO);
                    $doorProofUrl = $this->proofPreviewUrl($doorProof);
                    $containerProofUrl = $this->proofPreviewUrl($containerProof);
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
                        <div class="mb-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-400">Час виконання</div>
                        <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                            <span class="rounded-full border border-white/[0.12] bg-white/[0.04] px-2 py-0.5 text-slate-200">{{ $executionDateLabel }}</span>
                            <span class="rounded-full border border-white/[0.12] bg-white/[0.04] px-2 py-0.5 text-slate-200">{{ $serviceModeLabel }}</span>
                            @if($desiredWindowLabel)
                                <span class="rounded-full border border-sky-300/30 bg-sky-400/10 px-2 py-0.5 text-sky-200">{{ $desiredWindowLabel }}</span>
                            @endif
                            @if($validUntilLabel)
                                <span class="rounded-full border border-white/[0.12] bg-white/[0.04] px-2 py-0.5 text-slate-300">Активне до {{ $validUntilLabel }}</span>
                            @endif
                            @if($isUrgent)
                                <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-amber-300">Терміново</span>
                            @endif
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
                            Звʼязок з клієнтом
                        </button>
                    </div>

                    @if($order->status === \App\Models\Order::STATUS_IN_PROGRESS && $needsProofFlow)
                        <div
                            class="mt-3 rounded-2xl border border-white/[0.08] bg-[#0d1522] p-3 transition-shadow duration-500"
                            data-proof-section-for-order="{{ $order->id }}"
                            data-testid="proof-section-{{ $order->id }}"
                        >
                            <div class="mb-2 text-base font-bold text-amber-300">Зробіть 2 фото для завершення</div>
                            @if($proofReadyToSubmit)
                                <div class="mt-1 text-xs text-emerald-300">Фото додано. Тепер можна завершити замовлення.</div>
                            @endif
                            <div class="space-y-2.5">
                                <div
                                    data-testid="proof-card-door"
                                    x-data="proofCaptureCard({
                                        orderId: {{ $order->id }},
                                        proofType: '{{ \App\Models\OrderCompletionProof::TYPE_DOOR_PHOTO }}',
                                        uploadField: 'doorProofFiles.{{ $order->id }}',
                                        label: 'Фото у двері',
                                        initialPreview: @js($doorProofUrl),
                                        fallbackInputId: 'door-proof-fallback-{{ $order->id }}',
                                    })"
                                >
                                    <input x-ref="fallbackInput" id="door-proof-fallback-{{ $order->id }}" type="file" accept="image/*" capture="environment" class="hidden" @change="onFallbackSelected" />
                                    <button type="button" @click="openCapture" class="w-full rounded-xl border border-white/10 bg-white/[0.04] p-2.5 text-left">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="text-xs text-slate-200">Фото у двері</div>
                                            <span x-show="completed" class="text-emerald-300">✓</span>
                                        </div>
                                        <template x-if="previewUrl">
                                            <img :src="previewUrl" alt="Фото у двері" class="mt-2 h-24 w-full rounded-lg object-cover" />
                                        </template>
                                        <div x-show="!previewUrl" class="mt-2 rounded-lg border border-dashed border-white/15 px-3 py-5 text-center text-xs text-slate-400">Натисніть, щоб зробити фото зараз</div>
                                        <div x-show="fallbackNotice" class="mt-2 text-[11px] text-amber-300" x-text="fallbackNotice"></div>
                                        <div x-show="errorMessage" class="mt-2 text-[11px] text-rose-300" x-text="errorMessage"></div>
                                        <div class="mt-2 text-[11px] text-slate-400" x-show="completed">Натисніть для перезйомки</div>
                                    </button>

                                    <div x-show="open" x-cloak class="fixed inset-0 z-[80] bg-black/85">
                                        <div class="flex h-full flex-col p-4">
                                            <div class="flex items-center justify-between pb-3">
                                                <div class="text-sm font-semibold text-white" x-text="label"></div>
                                                <button type="button" class="text-sm text-slate-300" @click="closeCapture">Закрити</button>
                                            </div>
                                            <div class="relative flex-1 overflow-hidden rounded-2xl border border-white/10 bg-[#0b131d]">
                                                <video x-ref="video" autoplay playsinline class="h-full w-full object-cover"></video>
                                                <div x-show="isBusy" class="absolute inset-0 flex items-center justify-center bg-black/40 text-sm text-white">Завантаження...</div>
                                            </div>
                                            <div class="pt-3">
                                                <button type="button" class="courier-btn courier-btn-warning h-12 w-full" @click="captureFrame" :disabled="isBusy">Зробити фото</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    data-testid="proof-card-container"
                                    x-data="proofCaptureCard({
                                        orderId: {{ $order->id }},
                                        proofType: '{{ \App\Models\OrderCompletionProof::TYPE_CONTAINER_PHOTO }}',
                                        uploadField: 'containerProofFiles.{{ $order->id }}',
                                        label: 'Фото у контейнера',
                                        initialPreview: @js($containerProofUrl),
                                        fallbackInputId: 'container-proof-fallback-{{ $order->id }}',
                                    })"
                                >
                                    <input x-ref="fallbackInput" id="container-proof-fallback-{{ $order->id }}" type="file" accept="image/*" capture="environment" class="hidden" @change="onFallbackSelected" />
                                    <button type="button" @click="openCapture" class="w-full rounded-xl border border-white/10 bg-white/[0.04] p-2.5 text-left">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="text-xs text-slate-200">Фото у контейнера</div>
                                            <span x-show="completed" class="text-emerald-300">✓</span>
                                        </div>
                                        <template x-if="previewUrl">
                                            <img :src="previewUrl" alt="Фото у контейнера" class="mt-2 h-24 w-full rounded-lg object-cover" />
                                        </template>
                                        <div x-show="!previewUrl" class="mt-2 rounded-lg border border-dashed border-white/15 px-3 py-5 text-center text-xs text-slate-400">Натисніть, щоб зробити фото зараз</div>
                                        <div x-show="fallbackNotice" class="mt-2 text-[11px] text-amber-300" x-text="fallbackNotice"></div>
                                        <div x-show="errorMessage" class="mt-2 text-[11px] text-rose-300" x-text="errorMessage"></div>
                                        <div class="mt-2 text-[11px] text-slate-400" x-show="completed">Натисніть для перезйомки</div>
                                    </button>

                                    <div x-show="open" x-cloak class="fixed inset-0 z-[80] bg-black/85">
                                        <div class="flex h-full flex-col p-4">
                                            <div class="flex items-center justify-between pb-3">
                                                <div class="text-sm font-semibold text-white" x-text="label"></div>
                                                <button type="button" class="text-sm text-slate-300" @click="closeCapture">Закрити</button>
                                            </div>
                                            <div class="relative flex-1 overflow-hidden rounded-2xl border border-white/10 bg-[#0b131d]">
                                                <video x-ref="video" autoplay playsinline class="h-full w-full object-cover"></video>
                                                <div x-show="isBusy" class="absolute inset-0 flex items-center justify-center bg-black/40 text-sm text-white">Завантаження...</div>
                                            </div>
                                            <div class="pt-3">
                                                <button type="button" class="courier-btn courier-btn-warning h-12 w-full" @click="captureFrame" :disabled="isBusy">Зробити фото</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if($completionRequest?->status === \App\Models\OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION)
                                <div class="mt-2 text-xs text-emerald-300">Очікуємо підтвердження клієнта.</div>
                            @endif
                        </div>
                    @endif

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
                <div class="rounded-2xl border border-white/10 bg-[#0f1724]/[0.95] p-2 shadow-[0_-10px_28px_rgba(0,0,0,0.45)] backdrop-blur-sm" data-primary-order-cta>
                    @if($primaryActionOrder->status === \App\Models\Order::STATUS_ACCEPTED)
                        <button
                            type="button"
                            wire:click="start({{ $primaryActionOrder->id }})"
                            wire:loading.attr="disabled"
                            @if(! $online) disabled @endif
                            data-testid="primary-start-cta"
                            class="courier-btn courier-btn-warning h-12 w-full"
                        >
                            Почати виконання · #{{ $primaryActionOrder->id }}
                        </button>
                    @elseif($primaryActionOrder->status === \App\Models\Order::STATUS_IN_PROGRESS)
                        @php
                            $primaryCompletionRequest = $primaryActionOrder->completionRequest;
                            $primaryUploaded = $primaryCompletionRequest?->proofs?->pluck('proof_type')->all() ?? [];
                            $primaryNeedsProofFlow = $primaryActionOrder->completion_policy === \App\Models\Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM
                                && $primaryActionOrder->handover_type === \App\Models\Order::HANDOVER_DOOR;
                            $primaryProofReady = in_array(\App\Models\OrderCompletionProof::TYPE_DOOR_PHOTO, $primaryUploaded, true)
                                && in_array(\App\Models\OrderCompletionProof::TYPE_CONTAINER_PHOTO, $primaryUploaded, true);
                            $primaryAwaitingClient = $primaryCompletionRequest?->status === \App\Models\OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION;
                            $disableComplete = $primaryNeedsProofFlow && (! $primaryProofReady || $primaryAwaitingClient);
                        @endphp
                        <button
                            type="button"
                            wire:click="{{ $primaryNeedsProofFlow ? 'requestCompletionConfirmation' : 'complete' }}({{ $primaryActionOrder->id }})"
                            wire:loading.attr="disabled"
                            @if(! $online || $disableComplete) disabled @endif
                            data-testid="proof-complete-cta"
                            class="courier-btn courier-btn-success h-12 w-full text-base font-semibold"
                        >
                            {{ $primaryNeedsProofFlow ? 'Завершити виконання' : 'Завершити замовлення' }}
                        </button>
                    @endif
                </div>
            </div>
        @endif
    @endif
    @endif

    @if($activeTab === 'stats')
    <div class="mt-2 courier-surface border border-white/[0.08] p-4">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-100">Виконані замовлення</h3>
            <span class="text-xs text-slate-400">Останні {{ (int) config('courier_runtime.completed_stats.days', 14) }} днів</span>
        </div>

        @if(($completedStats ?? collect())->isEmpty())
            <div class="rounded-2xl border border-white/10 bg-white/[0.02] px-3 py-4 text-sm text-slate-400">
                Ще немає виконаних замовлень за обраний період.
            </div>
        @else
            <div class="space-y-2">
                @foreach($completedStats as $dayStat)
                    @php
                        $isOpen = in_array($dayStat['date'], $expandedCompletedStatDates, true);
                    @endphp
                    <div class="overflow-hidden rounded-2xl border border-white/10 bg-[#0d1522]">
                        <button
                            type="button"
                            wire:click="toggleCompletedStatDate('{{ $dayStat['date'] }}')"
                            class="flex w-full items-center justify-between gap-3 px-3 py-3 text-left active:bg-white/[0.03]"
                        >
                            <div class="text-sm font-medium text-slate-100">{{ $dayStat['label'] }}</div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-slate-100">{{ $dayStat['total_amount_formatted'] }}</span>
                                <svg class="h-4 w-4 text-slate-400 transition-transform {{ $isOpen ? 'rotate-180' : '' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>

                        @if($isOpen)
                            <div class="space-y-2 border-t border-white/10 px-3 py-3">
                                @foreach($dayStat['orders'] as $item)
                                    <div class="flex items-center justify-between gap-3 rounded-xl bg-white/[0.03] px-3 py-2">
                                        <div class="min-w-0">
                                            <div class="truncate text-sm text-slate-200">{{ $item['address_text'] }}</div>
                                            <div class="mt-0.5 text-xs text-slate-400">{{ $item['completed_time'] }}</div>
                                        </div>
                                        <div class="shrink-0 text-sm font-semibold text-slate-100">{{ $item['amount_formatted'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    @if($completionConfirmationOrderId)
        <div class="fixed inset-0 z-[90] bg-black/70" wire:key="completion-confirmation-modal">
            <div class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-md rounded-t-3xl border border-white/10 bg-[#0c131d] p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <div class="mx-auto mb-3 h-1.5 w-11 rounded-full bg-white/30"></div>
                <div class="text-base font-semibold text-slate-100">Ви завершили замовлення</div>
                <div class="mt-2 text-sm text-slate-300">Гроші зарахуються як тільки клієнт підтвердить виконання</div>
                <div class="mt-4 space-y-2">
                    <button type="button" wire:click="confirmCompletion" class="courier-btn courier-btn-success w-full" data-testid="proof-complete-confirm">Завершити замовлення</button>
                    <button type="button" wire:click="closeCompletionConfirmation" class="courier-btn courier-btn-secondary w-full">Повернутись</button>
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
    const revealProofForOrder = (orderId) => {
        const proofSection = document.querySelector(`[data-proof-section-for-order="${orderId}"]`);
        if (!proofSection) return;

        const cta = document.querySelector('[data-primary-order-cta]');
        const fixedBottomOffset = cta ? (cta.getBoundingClientRect().height + 24) : 0;
        const top = window.scrollY + proofSection.getBoundingClientRect().top - 16;
        window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });

        window.setTimeout(() => {
            const rect = proofSection.getBoundingClientRect();
            const visibleBottom = window.innerHeight - fixedBottomOffset;
            if (rect.bottom > visibleBottom) {
                window.scrollBy({ top: rect.bottom - visibleBottom + 12, behavior: 'smooth' });
            }
        }, 220);

        proofSection.classList.remove('proof-section-pulse');
        window.requestAnimationFrame(() => proofSection.classList.add('proof-section-pulse'));
        window.setTimeout(() => proofSection.classList.remove('proof-section-pulse'), 1700);
    };

    if (!window.__poofProofRevealBound) {
        window.addEventListener('courier-proof:reveal', (event) => {
            const orderId = event?.detail?.orderId;
            if (!orderId) return;
            window.setTimeout(() => revealProofForOrder(orderId), 80);
        });
        window.__poofProofRevealBound = true;
    }

    Alpine.data('proofCaptureCard', (config) => ({
        ...config,
        open: false,
        stream: null,
        isBusy: false,
        previewUrl: config.initialPreview ?? null,
        completed: !!config.initialPreview,
        fallbackNotice: null,
        errorMessage: null,
        async openCapture() {
            this.errorMessage = null;
            this.fallbackNotice = null;

            const mockCapture = window.__poofCameraCaptureMock;
            if (typeof mockCapture === 'function') {
                const blob = await mockCapture(this.proofType);
                await this.uploadBlob(blob, 'camera');
                return;
            }

            if (!navigator.mediaDevices?.getUserMedia) {
                this.useFallback('Камера недоступна у цьому браузері. Використайте системний вибір фото.');
                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                this.open = true;
                this.$nextTick(() => {
                    this.$refs.video.srcObject = this.stream;
                });
            } catch (e) {
                this.useFallback('Немає доступу до камери. Використайте резервний вибір фото.');
            }
        },
        closeCapture() {
            this.open = false;
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
            }
            this.stream = null;
        },
        async captureFrame() {
            if (!this.$refs.video?.videoWidth) {
                this.errorMessage = 'Не вдалося отримати кадр з камери.';
                return;
            }

            this.isBusy = true;
            try {
                const canvas = document.createElement('canvas');
                canvas.width = this.$refs.video.videoWidth;
                canvas.height = this.$refs.video.videoHeight;
                canvas.getContext('2d').drawImage(this.$refs.video, 0, 0, canvas.width, canvas.height);
                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
                if (!blob) {
                    throw new Error('capture_failed');
                }
                await this.uploadBlob(blob, 'camera');
                this.closeCapture();
            } catch (e) {
                this.errorMessage = 'Не вдалося зробити фото. Спробуйте ще раз.';
            } finally {
                this.isBusy = false;
            }
        },
        useFallback(message) {
            this.fallbackNotice = message;
            this.$refs.fallbackInput.click();
        },
        async onFallbackSelected(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            await this.uploadBlob(file, 'file_fallback');
            event.target.value = '';
        },
        async uploadBlob(blob, capturedVia) {
            this.errorMessage = null;
            this.isBusy = true;

            const file = blob instanceof File
                ? blob
                : new File([blob], `${this.proofType}-${Date.now()}.jpg`, { type: blob.type || 'image/jpeg' });

            await new Promise((resolve) => {
                this.$wire.upload(this.uploadField, file, () => {
                    this.$wire.uploadProof(this.orderId, this.proofType, capturedVia, new Date().toISOString())
                        .then(() => {
                            this.previewUrl = URL.createObjectURL(file);
                            this.completed = true;
                            resolve();
                        })
                        .catch(() => {
                            this.errorMessage = 'Не вдалося завантажити фото.';
                            resolve();
                        });
                }, () => {
                    this.errorMessage = 'Помилка підготовки фото до завантаження.';
                    resolve();
                });
            });

            this.isBusy = false;
        },
    }));
</script>
@endscript
