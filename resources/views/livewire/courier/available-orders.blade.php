<div class="relative flex-1 overflow-hidden rounded-[1.6rem] border border-white/10 bg-[#0b1018] shadow-[0_18px_40px_rgba(0,0,0,0.42)]" wire:poll.10s>

    <div
        class="relative h-[calc(100dvh-168px)] w-full overflow-hidden rounded-[1.6rem] bg-[#070b12]"
        data-map-bootstrap='@json($mapBootstrap ?? null)'
    >
        <div wire:ignore id="map" class="absolute inset-0 rounded-[1.6rem]"></div>
        <div class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-[#06090e]/80 to-transparent"></div>
    </div>

    <div class="absolute inset-x-3 bottom-3 z-30 space-y-3">
        @if($activeOrder)
            <div class="rounded-3xl border border-amber-300/40 bg-[#17120a]/95 p-4 shadow-2xl backdrop-blur-sm">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-200/80">Активне замовлення</div>
                        <div class="mt-1 text-lg font-bold leading-tight text-amber-50">#{{ $activeOrder->id }}</div>
                        <div class="mt-1 text-xs text-amber-100/70">Завершіть поточне, щоб отримати нове</div>
                    </div>

                    <a
                        href="{{ route('courier.my-orders') }}"
                        wire:navigate
                        class="shrink-0 rounded-2xl bg-amber-300 px-4 py-2 text-sm font-bold text-[#1d1508] transition hover:bg-amber-200"
                    >
                        Перейти
                    </a>
                </div>
            </div>
        @elseif($online)
            <div class="rounded-3xl border border-white/10 bg-[#101722]/92 p-5 shadow-2xl backdrop-blur-sm">
                <div class="flex items-center gap-3">
                    <div class="h-5 w-5 animate-spin rounded-full border-2 border-poof border-t-transparent"></div>

                    <div>
                        <div class="text-sm font-semibold text-white">Пошук замовлень</div>
                        <div class="mt-1 text-xs text-slate-400">Ми перевіряємо найближчі запити для вас</div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if(! $online && ! $activeOrder)
        <div class="absolute inset-0 z-20 flex items-center justify-center rounded-[1.6rem] bg-black/65 backdrop-blur-[2px]">
            <div class="mx-4 w-full max-w-[270px] rounded-3xl border border-white/10 bg-[#0f1520]/95 p-6 text-center shadow-xl">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-white/5 text-slate-300">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 3l18 18" />
                        <path d="M10.6 10.6A3 3 0 0 0 15 15" />
                        <path d="M9 5.2A8 8 0 0 1 20 12" />
                        <path d="M4.4 9.8A8 8 0 0 0 12 20" />
                    </svg>
                </div>
                <div class="font-semibold text-white">Ви не на лінії</div>
                <div class="mt-1 text-sm text-slate-400">Увімкніть статус у шапці, щоб отримувати замовлення</div>
            </div>
        </div>
    @endif

</div>
