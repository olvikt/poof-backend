<button
    wire:poll.10s="syncOnlineState"
    type="button"
    wire:click="toggleOnlineState"
    wire:loading.attr="disabled"
    wire:target="toggleOnlineState"
    @if($busyWithActiveOrder) disabled aria-disabled="true" @endif
    class="inline-flex min-w-[148px] items-center justify-center gap-2 rounded-xl border px-3 py-2 text-xs font-semibold tracking-[0.01em] shadow-sm transition disabled:cursor-not-allowed disabled:opacity-80 {{ $online ? 'border-emerald-300/55 bg-emerald-400/18 text-emerald-50 shadow-emerald-500/10' : 'border-slate-300/35 bg-slate-700/20 text-slate-100 shadow-black/20' }}"
>
    <span wire:loading.remove wire:target="toggleOnlineState" class="whitespace-nowrap">
        {{ $online ? '🟢 На лінії' : '⚫ Не на лінії' }}
    </span>

    <span wire:loading wire:target="toggleOnlineState" class="inline-flex items-center gap-1">
        <span class="h-3.5 w-3.5 animate-spin rounded-full border border-current border-t-transparent"></span>
        Оновлення
    </span>
</button>
