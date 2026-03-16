<div class="relative flex-1 overflow-hidden rounded-2xl">

    <div class="relative h-[calc(100dvh-160px)] w-full overflow-hidden rounded-2xl bg-zinc-950">
        <div wire:ignore id="map" class="absolute inset-0 rounded-2xl"></div>
    </div>

    <div class="absolute bottom-4 left-3 right-3 z-30 space-y-3">
        @if($activeOrder)
            <div class="bg-yellow-400 text-black rounded-2xl px-4 py-3 shadow-2xl border border-yellow-300">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold uppercase tracking-wide opacity-80">Активне замовлення</div>
                        <div class="text-base font-extrabold leading-tight">#{{ $activeOrder->id }}</div>
                        <div class="text-xs mt-1 opacity-75">Завершіть його, щоб отримати нове</div>
                    </div>

                    <a
                        href="{{ route('courier.my-orders') }}"
                        wire:navigate
                        class="shrink-0 bg-black text-white px-4 py-2 rounded-xl text-sm font-bold"
                    >
                        Перейти →
                    </a>
                </div>
            </div>
        @elseif($online)
            <div class="bg-gray-900/95 backdrop-blur border border-gray-700 rounded-3xl p-5 shadow-2xl">
                <div class="flex items-center gap-3">
                    <div class="animate-spin h-5 w-5 border-2 border-yellow-400 border-t-transparent rounded-full"></div>

                    <div>
                        <div class="font-semibold text-white">Пошук замовлень...</div>
                        <div class="text-xs text-gray-400 mt-1">Очікуйте, ми шукаємо клієнтів поруч</div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if(! $online && ! $activeOrder)
        <div class="absolute inset-0 z-20 flex items-center justify-center rounded-2xl bg-black/70 backdrop-blur transition-opacity duration-300">
            <div class="bg-gray-900 border border-gray-700 rounded-2xl p-6 text-center shadow-xl">
                <div class="text-3xl mb-2">🛑</div>
                <div class="font-semibold text-white">Ви не на лінії</div>
                <div class="text-sm text-gray-400 mt-1">Увімкніть статус для отримання замовлень</div>
            </div>
        </div>
    @endif

</div>
