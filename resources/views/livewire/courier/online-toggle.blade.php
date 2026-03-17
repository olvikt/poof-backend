<button
    wire:poll.10s="syncOnlineState"
    type="button"
    wire:click="toggleOnlineState"
    wire:loading.attr="disabled"
    wire:target="toggleOnlineState"
    @if($busyWithActiveOrder) disabled aria-disabled="true" @endif
    class="flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold transition disabled:opacity-80 disabled:cursor-not-allowed
        {{ $online ? 'bg-emerald-500 text-black' : 'bg-zinc-700 text-gray-300' }}"
>
    <span class="text-xs" wire:loading.remove wire:target="toggleOnlineState">
        {{ $online ? '🟢 На лінії' : '⚫ Не на лінії' }}
    </span>

    <span class="text-xs" wire:loading wire:target="toggleOnlineState">
        Оновлення...
    </span>
</button>
