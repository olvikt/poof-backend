<button
    wire:poll.10s="syncOnlineState"
    type="button"
    wire:click="toggleOnlineState"
    wire:loading.attr="disabled"
    wire:target="toggleOnlineState"
    @if($busyWithActiveOrder) disabled aria-disabled="true" @endif
    class="courier-chip-control min-w-[124px] {{ $online ? 'border-emerald-300/55 bg-emerald-500/18 text-emerald-100' : 'border-slate-400/40 bg-slate-700/30 text-slate-100 hover:bg-slate-700/45' }}"
>
    <span wire:loading.remove wire:target="toggleOnlineState" class="whitespace-nowrap">
        {{ $online ? '🟢 На лінії' : '⚫ Не на лінії' }}
    </span>

    <span wire:loading wire:target="toggleOnlineState" class="inline-flex items-center gap-1">
        <span class="h-3.5 w-3.5 animate-spin rounded-full border border-current border-t-transparent"></span>
        Оновлення
    </span>
</button>
