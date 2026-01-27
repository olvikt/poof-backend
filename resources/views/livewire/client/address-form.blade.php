<div class="space-y-4">
    <div>
        <label class="text-xs text-gray-400">Адреса</label>
        <input
            type="text"
            wire:model.defer="address"
            class="poof-input w-full"
            placeholder="Вулиця, будинок"
        >
    </div>

    <div>
        <label class="text-xs text-gray-400">Місто</label>
        <input
            type="text"
            wire:model.defer="city"
            class="poof-input w-full"
        >
    </div>

    <div class="grid grid-cols-3 gap-3">
        <div>
            <label class="text-xs text-gray-400">Підʼїзд</label>
            <input
                type="text"
                wire:model.defer="entrance"
                class="poof-input w-full"
            >
        </div>

        <div>
            <label class="text-xs text-gray-400">Поверх</label>
            <input
                type="text"
                wire:model.defer="floor"
                class="poof-input w-full"
            >
        </div>

        <div>
            <label class="text-xs text-gray-400">Квартира</label>
            <input
                type="text"
                wire:model.defer="apartment"
                class="poof-input w-full"
            >
        </div>
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
