<div class="relative h-[calc(100dvh-var(--courier-header-h)-var(--courier-nav-h)-env(safe-area-inset-bottom)-var(--courier-screen-bottom-gap))] min-h-[440px] w-full overflow-hidden" wire:poll.{{ $pollIntervalSeconds }}s data-map-bootstrap='@json($mapBootstrap ?? null)'>
    <div class="sr-only" aria-hidden="true" data-map-bootstrap='@json($mapBootstrap ?? null)'></div>
    <div class="relative h-full w-full overflow-hidden bg-[#070b12]" data-map-bootstrap='@json($mapBootstrap ?? null)'>
        <div wire:ignore id="map" class="absolute inset-0" data-map-bootstrap='@json($mapBootstrap ?? null)'></div>
        <div class="pointer-events-none absolute inset-x-0 top-0 h-20 bg-gradient-to-b from-[#06090e]/[0.75] to-transparent"></div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-[#06090e]/[0.60] to-transparent"></div>
    </div>

    @if(! $online && ! $activeOrder)
        <div class="absolute inset-0 z-20 bg-black/55"></div>
        <div class="absolute inset-x-4 top-1/2 z-30 -translate-y-1/2 rounded-3xl border border-white/10 bg-[#0f1520]/[0.95] p-5 shadow-2xl backdrop-blur-sm">
            <div class="text-lg font-semibold text-white">Ви зараз офлайн</div>
            <div class="mt-2 text-sm leading-relaxed text-slate-300">Увімкніть статус онлайн, щоб отримувати замовлення.</div>
        </div>
    @endif

    <div class="absolute inset-x-3 bottom-[calc(var(--courier-nav-h)+env(safe-area-inset-bottom)+0.2rem)] z-30">
        @if($activeOrder)
            <div class="rounded-2xl border border-amber-200/35 bg-[#171003]/[0.94] p-3.5 shadow-[0_18px_44px_rgba(0,0,0,0.42)]">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-[0.08em] text-amber-200">Активне замовлення</div>
                        <div class="mt-1 text-base font-bold text-amber-50">#{{ $activeOrder->id }}</div>
                        <div class="mt-0.5 text-xs leading-relaxed text-amber-100/85">Завершіть активні замовлення, щоб знову отримувати нові.</div>
                    </div>

                    <a
                        href="{{ route('courier.my-orders') }}"
                        wire:navigate
                        class="courier-btn courier-btn-warning h-10 shrink-0 px-3.5 text-xs"
                    >
                        Відкрити
                    </a>
                </div>
            </div>
        @elseif(!($isVerified ?? false))
            <div class="rounded-2xl border border-amber-200/35 bg-[#171003]/[0.94] p-3.5 shadow-[0_18px_44px_rgba(0,0,0,0.42)]">
                <div class="text-sm font-semibold text-amber-50">Перед тим, як отримувати замовлення, потрібно пройти верифікацію.</div>
                <div class="mt-1 text-xs text-amber-100/85">Ваш профіль очікує верифікації. Після підтвердження адміністратором ви зможете приймати замовлення.</div>
                <a href="{{ route('courier.profile') }}" wire:navigate class="courier-btn courier-btn-warning mt-3 inline-flex h-10 items-center px-3 text-xs">Перейти до профілю</a>
            </div>
        @elseif($online)
            <div class="rounded-2xl border border-slate-100/12 bg-[#101722]/[0.92] p-3.5 shadow-[0_18px_44px_rgba(0,0,0,0.4)]">
                @if($emptyState['has_pending_offer'] ?? false)
                    {{-- do not render empty-state copy; pending offer UI is rendered elsewhere --}}
                @elseif($emptyState['location_stale'] ?? false)
                    <div class="text-sm font-semibold text-white">Очікуємо вашу геолокацію</div>
                    <div class="mt-0.5 text-xs text-slate-400">Дозвольте доступ до геолокації або оновіть сторінку.</div>
                @elseif(($emptyState['has_pending_offer'] ?? false) === false && ($emptyState['show_neutral_searching_hint'] ?? false))
                    <div class="text-sm font-semibold text-white">Замовлення поруч є, ми перевіряємо доступність. Залишайтесь онлайн.</div>
                @elseif(($emptyState['nearby_soon_count'] ?? 0) > 0)
                    <div class="text-sm font-semibold text-white">У вашому районі є {{ $emptyState['nearby_soon_count'] }} замовлень, вони скоро стануть доступні</div>
                    <div class="mt-0.5 text-xs text-slate-400">Найближче: {{ optional($emptyState['nearby_soon_nearest_at'] ?? null)?->format('H:i') ?? '—' }}</div>
                @else
                    <div class="text-sm font-semibold text-white">Зараз доступних замовлень немає</div>
                    <div class="mt-0.5 text-xs text-slate-400">Ми автоматично покажемо нове замовлення, щойно воно зʼявиться у вашому районі.</div>
                    <div class="mt-1 text-xs text-slate-400">Залишайтесь онлайн і не закривайте застосунок.</div>
                @endif
            </div>
        @endif
    </div>
</div>
