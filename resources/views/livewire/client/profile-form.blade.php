<div class="space-y-4">
    <div>
        <label class="text-xs text-gray-400">ПІБ</label>
        <input
            type="text"
            wire:model.defer="name"
            autocomplete="name"
            class="poof-input w-full"
        >
    </div>

    <div>
        <label class="text-xs text-gray-400">Телефон</label>
        <input
            type="text"
            wire:model.defer="phone"
            autocomplete="tel"
            class="poof-input w-full"
        >
    </div>

    <div>
        <label class="text-xs text-gray-400">Email</label>
        <input
            type="email"
            wire:model.defer="email"
            autocomplete="email"
            class="poof-input w-full"
        >
    </div>

    <button
        type="button"
        wire:click="save"
        wire:loading.attr="disabled"
        wire:target="save"
        class="w-full bg-yellow-400 text-black font-bold py-3 rounded-xl"
    >
        <span wire:loading.remove wire:target="save">Зберегти</span>
        <span wire:loading wire:target="save">Збереження…</span>
    </button>
</div>
