<div class="relative h-[calc(100dvh-118px)] w-full pb-20" wire:poll.10s data-map-bootstrap='@json($mapBootstrap ?? null)'>
    <div class="sr-only" aria-hidden="true" data-map-bootstrap='@json($mapBootstrap ?? null)'></div>
    <div class="relative h-full w-full overflow-hidden bg-[#070b12]" data-map-bootstrap='@json($mapBootstrap ?? null)'>
        <div wire:ignore id="map" class="absolute inset-0" data-map-bootstrap='@json($mapBootstrap ?? null)'></div>
        <div class="pointer-events-none absolute inset-x-0 top-0 h-20 bg-gradient-to-b from-[#06090e]/75 to-transparent"></div>
    </div>

    @if(! $online && ! $activeOrder)
        <div class="absolute inset-0 z-20 bg-black/55"></div>
        <div class="absolute inset-x-4 top-1/2 z-30 -translate-y-1/2 rounded-3xl border border-white/10 bg-[#0f1520]/95 p-5 shadow-2xl backdrop-blur-sm">
            <div class="text-lg font-semibold text-white">Ви не на лінії</div>
            <div class="mt-2 text-sm leading-relaxed text-slate-300">Щоб отримувати нові замовлення, увімкніть статус курʼєра кнопкою у верхній панелі.</div>
            <div class="mt-4 text-xs text-slate-400">Після переходу онлайн ми автоматично почнемо пошук найближчих пропозицій.</div>
        </div>
    @endif

    <div class="absolute inset-x-3 bottom-[calc(5.75rem+env(safe-area-inset-bottom))] z-30">
        @if($activeOrder)
            <div class="rounded-3xl border border-amber-200/50 bg-[#171003]/97 p-4 shadow-[0_20px_50px_rgba(0,0,0,0.45)]">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-[0.08em] text-amber-200">Активне замовлення</div>
                        <div class="mt-1 text-lg font-bold text-amber-50">#{{ $activeOrder->id }}</div>
                        <div class="mt-1 text-xs leading-relaxed text-amber-100/90">Завершіть поточну доставку, щоб знову отримувати нові замовлення.</div>
                    </div>

                    <a
                        href="{{ route('courier.my-orders') }}"
                        wire:navigate
                        class="shrink-0 rounded-2xl bg-amber-300 px-4 py-2 text-sm font-semibold text-[#1d1508] shadow-[0_10px_24px_rgba(252,211,77,0.25)] transition hover:bg-amber-200"
                    >
                        Відкрити
                    </a>
                </div>
            </div>
        @elseif($online)
            <div class="rounded-3xl border border-slate-100/15 bg-[#101722]/95 p-4 shadow-[0_20px_50px_rgba(0,0,0,0.42)]">
                <div class="flex items-center gap-3">
                    <div class="h-5 w-5 animate-spin rounded-full border-2 border-poof border-t-transparent"></div>
                    <div>
                        <div class="text-sm font-semibold text-white">Пошук замовлень...</div>
                        <div class="mt-1 text-xs text-slate-400">Залишайтесь на мапі — нова пропозиція зʼявиться тут миттєво.</div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
