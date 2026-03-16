<button
    wire:click="toggleOnlineState"
    class="flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold transition
        {{ $online ? 'bg-emerald-500 text-black' : 'bg-zinc-700 text-gray-300' }}"
>
    <span class="text-xs">
        {{ $online ? '🟢 На лінії' : '⚫ Не на лінії' }}
    </span>
</button>
