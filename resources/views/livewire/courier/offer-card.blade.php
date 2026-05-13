<div wire:poll.{{ $pollIntervalSeconds }}s="loadOffer">

    @if ($offer)
        @php
            $order       = $offer->order;
            $isStack     = $offer->isStack();
            $distance    = $this->distanceKm;
            $windowLabel = $order?->service_mode === \App\Models\Order::SERVICE_MODE_ASAP
                ? 'Якнайшвидше'
                : (($order?->window_from_at?->format('H:i') ?? $order?->scheduled_time_from ?? '—') . '–' . ($order?->window_to_at?->format('H:i') ?? $order?->scheduled_time_to ?? '—'));
            $warningMinutes = max(1, (int) config('order_promise.courier_urgency_warning_minutes', 30));
            $isUrgent = $order?->valid_until_at?->diffInMinutes(now(), false) !== null
                && $order?->valid_until_at?->isFuture()
                && $order?->valid_until_at?->diffInMinutes(now()) <= $warningMinutes;
        @endphp

        <div class="fixed bottom-[calc(var(--courier-nav-h)+env(safe-area-inset-bottom)+0.2rem)] left-0 right-0 z-50 pointer-events-none">
            <div class="max-w-md mx-auto px-4 pointer-events-auto">
                <div
                    x-data="offerCountdown({ expiresAt: @js($offer->expires_at?->toISOString()), serverNow: @js(now()->toISOString()), totalSeconds: 45 })"
                    x-init="init()"
                    class="relative rounded-3xl bg-gradient-to-br from-gray-900 to-gray-800 border border-gray-700/70 shadow-2xl overflow-hidden"
                >
                    <div class="absolute top-0 left-0 right-0 h-1 bg-poof"></div>

                    <div class="p-5">
                        <div class="mt-2 mb-4 text-center border-b border-gray-600">
                            <div class="text-4xl pb-4 font-black text-green-400 tracking-tight">{{ $order?->price ?? '—' }} ₴</div>
                        </div>

                        <div class="mb-4 rounded-2xl border px-4 py-3" :class="urgencyClass">
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase tracking-wide text-gray-300">Час на підтвердження</span>
                                <span class="text-xs font-semibold" :class="urgencyTextClass" x-text="urgencyLabel"></span>
                            </div>
                            <div class="mt-1 text-5xl font-black tabular-nums" :class="urgencyTextClass" x-text="formatted"></div>
                            <div class="mt-3 h-2 rounded-full bg-white/10 overflow-hidden">
                                <div class="h-full transition-all duration-700" :class="progressClass" :style="`width: ${progress}%`"></div>
                            </div>
                        </div>

                        <div class="flex items-start justify-between">
                            <div>
                                <div class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full {{ $isStack ? 'bg-purple-600/20 text-purple-400' : 'bg-green-600/20 text-green-400' }}">
                                    {{ $isStack ? '📦 Додаткове поруч' : '🆕 Нове замовлення' }}
                                </div>
                                <div class="mt-3 text-lg font-extrabold text-white">Замовлення #{{ $offer->order_id }}</div>
                            </div>
                            <div class="text-right text-xs text-gray-400">TTL: {{ $offer->expires_at?->format('H:i:s') ?? '—' }}</div>
                        </div>

                        <div class="mt-5 space-y-2 text-sm">
                            <div class="flex items-center justify-between text-gray-300"><span>⏰ {{ $windowLabel }}</span></div>
                            <div class="flex items-center justify-between text-gray-300"><span>🕓 Створено: {{ optional($order?->created_at)->format('d.m H:i') ?? '—' }}</span></div>
                            <div class="flex items-center justify-between text-gray-300">
                                <span>⌛ Активне до: {{ optional($order?->valid_until_at)->format('d.m H:i') ?? '—' }}</span>
                                @if($isUrgent)
                                    <span class="ml-2 rounded-full bg-amber-500/20 px-2 py-0.5 text-[11px] font-semibold text-amber-300">Терміново</span>
                                @endif
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-2 gap-3">
                            <button type="button" wire:click="reject" class="courier-btn courier-btn-secondary h-12 border-gray-600 bg-gray-800 text-gray-200 hover:bg-gray-700">Пропустити</button>
                            <button type="button" wire:click="accept" data-e2e="courier-accept-offer" class="courier-btn courier-btn-warning h-12 font-bold" :disabled="isExpired" x-bind:class="{ 'opacity-50 cursor-not-allowed': isExpired }">Прийняти</button>
                        </div>
                        <div x-show="isExpired" class="mt-3 text-center text-sm font-semibold text-red-300">Офер більше недоступний.</div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
window.offerCountdown = ({ expiresAt, serverNow, totalSeconds }) => ({
    nowMs: Date.parse(serverNow), expiresAtMs: Date.parse(expiresAt), totalSeconds,
    leftSeconds: totalSeconds, isExpired: false, tickId: null,
    init() { this.sync(); this.tickId = setInterval(() => { this.nowMs += 1000; this.sync(); }, 1000); document.addEventListener('visibilitychange', () => { if (!document.hidden) this.syncWithClientNow(); }); },
    syncWithClientNow() { const drift = Date.now() - this.nowMs; this.nowMs += drift; this.sync(); },
    sync() { const remaining = Math.max(0, Math.ceil((this.expiresAtMs - this.nowMs) / 1000)); this.leftSeconds = Math.min(this.totalSeconds, remaining); this.isExpired = remaining <= 0; },
    get formatted() { const sec = Math.max(0, this.leftSeconds); return `00:${String(sec).padStart(2, '0')}`; },
    get progress() { return Math.max(0, Math.min(100, (this.leftSeconds / this.totalSeconds) * 100)); },
    get urgencyLabel() { if (this.leftSeconds < 5) return 'КРИТИЧНО'; if (this.leftSeconds < 15) return 'УВАГА'; return 'Нормально'; },
    get urgencyClass() { if (this.leftSeconds < 5) return 'border-red-400/70 bg-red-500/10'; if (this.leftSeconds < 15) return 'border-amber-400/70 bg-amber-500/10'; return 'border-emerald-400/60 bg-emerald-500/10'; },
    get urgencyTextClass() { if (this.leftSeconds < 5) return 'text-red-300'; if (this.leftSeconds < 15) return 'text-amber-300'; return 'text-emerald-300'; },
    get progressClass() { if (this.leftSeconds < 5) return 'bg-red-400'; if (this.leftSeconds < 15) return 'bg-amber-400'; return 'bg-emerald-400'; },
});
</script>
@endscript
