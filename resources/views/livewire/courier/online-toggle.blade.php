<button
    wire:poll.10s="syncOnlineState"
    type="button"
    wire:click="toggleOnlineState"
    wire:loading.attr="disabled"
    wire:target="toggleOnlineState"
    @if($busyWithActiveOrder) disabled aria-disabled="true" @endif
    class="inline-flex min-w-[152px] items-center justify-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold tracking-[0.01em] shadow-sm transition disabled:cursor-not-allowed disabled:opacity-85 {{ $online ? 'border-emerald-200/75 bg-emerald-400/30 text-emerald-50 shadow-emerald-500/20' : 'border-slate-200/65 bg-slate-700/45 text-white shadow-black/35' }}"
>
    <span wire:loading.remove wire:target="toggleOnlineState" class="whitespace-nowrap">
        {{ $online ? '🟢 На лінії' : '⚫ Не на лінії' }}
    </span>

    <span wire:loading wire:target="toggleOnlineState" class="inline-flex items-center gap-1">
        <span class="h-3.5 w-3.5 animate-spin rounded-full border border-current border-t-transparent"></span>
        Оновлення
    </span>
</button>
