<button
    wire:poll.10s="syncOnlineState"
    type="button"
    wire:click="toggleOnlineState"
    wire:loading.attr="disabled"
    wire:target="toggleOnlineState"
    @if($busyWithActiveOrder) disabled aria-disabled="true" @endif
    class="inline-flex min-w-[138px] items-center justify-center gap-2 rounded-2xl border px-3 py-2 text-xs font-semibold tracking-wide transition disabled:cursor-not-allowed disabled:opacity-80 {{ $online ? 'border-emerald-400/30 bg-emerald-400/15 text-emerald-200' : 'border-white/15 bg-white/[0.04] text-slate-300' }}"
>
    <span class="flex h-2.5 w-2.5 rounded-full {{ $online ? 'bg-emerald-300' : 'bg-slate-500' }}" wire:loading.remove wire:target="toggleOnlineState"></span>

    <span wire:loading.remove wire:target="toggleOnlineState">
        {{ $online ? 'На лінії' : 'Не на лінії' }}
    </span>

    <span wire:loading wire:target="toggleOnlineState" class="inline-flex items-center gap-1">
        <span class="h-3.5 w-3.5 animate-spin rounded-full border border-current border-t-transparent"></span>
        Оновлення
    </span>
</button>
