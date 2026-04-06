<div wire:poll.10s="syncOnlineState('poll')">
    <div class="flex items-center gap-3 rounded-2xl border border-white/15 bg-[#111a28] px-3 py-2 shadow-[0_14px_26px_rgba(0,0,0,0.42),inset_0_1px_0_rgba(255,255,255,0.08)] ring-1 ring-black/20">
        <button
            type="button"
            wire:key="courier-online-toggle-button"
            wire:click.prevent.stop="toggleOnlineState"
            wire:loading.attr="disabled"
            wire:target="toggleOnlineState,syncOnlineState"
            data-testid="courier-online-toggle"
            data-e2e="courier-online-toggle"
            data-e2e-online-state="{{ $online ? 'online' : 'offline' }}"
            data-e2e-busy="{{ $busyWithActiveOrder ? '1' : '0' }}"
            @if($busyWithActiveOrder) disabled aria-disabled="true" @endif
            class="group relative h-8 w-[54px] shrink-0 rounded-full border transition-all duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300/70 disabled:cursor-not-allowed {{ $online ? 'border-emerald-300/60 bg-gradient-to-r from-emerald-400 to-emerald-500 shadow-[0_8px_18px_rgba(16,185,129,0.38),inset_0_0_0_1px_rgba(255,255,255,0.22)]' : 'border-slate-500/50 bg-gradient-to-r from-slate-600/85 to-slate-700/90 shadow-[0_7px_16px_rgba(2,6,23,0.5),inset_0_0_0_1px_rgba(255,255,255,0.09)]' }} {{ $busyWithActiveOrder ? 'opacity-85' : 'hover:scale-[1.01] active:scale-[0.99]' }}"
        >
            <span class="sr-only">{{ $online ? 'На лінії' : 'Не на лінії' }}</span>
            <span class="absolute inset-y-0 left-[2px] top-[2px] h-6 w-6 rounded-full bg-white shadow-[0_3px_8px_rgba(0,0,0,0.35)] transition-transform duration-200 ease-out {{ $online ? 'translate-x-[24px]' : 'translate-x-0' }}"></span>
        </button>

        <div class="min-w-[102px] text-right">
            <div class="text-sm font-semibold leading-4 {{ $online ? 'text-emerald-200' : 'text-slate-200' }}">
                <span wire:loading.remove wire:target="toggleOnlineState">
                    {{ $online ? 'На лінії' : 'Не на лінії' }}
                </span>
                <span wire:loading wire:target="toggleOnlineState" class="inline-flex items-center justify-end gap-1 text-slate-100">
                    <span class="h-3.5 w-3.5 animate-spin rounded-full border border-current border-t-transparent"></span>
                    Оновлення
                </span>
            </div>
            <div class="mt-1 text-xs font-medium text-slate-400">
                Баланс: <span class="font-semibold text-slate-100">{{ $balanceSummary['balance_formatted'] }}</span>
            </div>
        </div>
    </div>
</div>
