<form wire:submit.prevent="save" class="space-y-5">

    {{-- 🔖 Тип адреси --}}
    <div class="flex gap-2">
        <button
            type="button"
            wire:click="$set('label','home')"
            class="px-4 py-2 rounded-xl text-sm font-semibold transition
                {{ $label === 'home'
                    ? 'bg-yellow-400 text-black'
                    : 'bg-neutral-800 text-gray-300 hover:bg-neutral-700' }}"
        >
            Дім
        </button>

        <button
            type="button"
            wire:click="$set('label','work')"
            class="px-4 py-2 rounded-xl text-sm font-semibold transition
                {{ $label === 'work'
                    ? 'bg-yellow-400 text-black'
                    : 'bg-neutral-800 text-gray-300 hover:bg-neutral-700' }}"
        >
            Робота
        </button>

        <button
            type="button"
            wire:click="$set('label','other')"
            class="px-4 py-2 rounded-xl text-sm font-semibold transition
                {{ $label === 'other'
                    ? 'bg-yellow-400 text-black'
                    : 'bg-neutral-800 text-gray-300 hover:bg-neutral-700' }}"
        >
            Інше
        </button>
    </div>

    {{-- 🏷 Назва --}}
    <div>
        <label class="text-xs text-gray-400">Назва (опційно)</label>
        <input
            type="text"
            wire:model.defer="title"
            placeholder="Напр. Дім, Офіс"
            class="poof-input w-full"
        >
        @error('title')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror
    </div>

{{-- 📍 Адреса --}}
<div class="relative">
    <label class="text-xs text-gray-400">Адреса</label>

    <div class="flex gap-2">
        {{-- 🔍 Вулиця / район (≈ 3/4) --}}
        <div class="relative flex-1">
            <input
                type="text"
                wire:model.live="search"
                wire:keydown.enter.prevent
                placeholder="Вулиця, район…"
                class="poof-input w-full"
            >

            {{-- 🔽 Suggestions --}}
            @if (!empty($suggestions))
                <div
                    class="absolute z-50 mt-1 w-full rounded-xl
                           bg-neutral-900 border border-neutral-700
                           shadow-xl overflow-hidden"
                >
                    @foreach ($suggestions as $item)
                        <button
                            type="button"
                            wire:click="selectPlace('{{ $item['place_id'] }}')"
                            class="block w-full text-left px-4 py-2 text-sm
                                   hover:bg-neutral-800 transition"
                        >
                            {{ $item['label'] }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- 🏠 Будинок (≈ 1/4) --}}
        <div class="w-20 shrink-0">
            <input
                type="text"
                wire:model.defer="house"
                placeholder="Буд."
                class="poof-input w-full text-center px-2 py-2 text-sm"
            >
        </div>
    </div>

    <p class="mt-1 text-xs text-gray-500">
        Якщо номер будинку не зʼявився — введіть його вручну
    </p>

    @error('search')
        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
    @enderror
</div>




    {{-- 🧩 Додатково --}}
    <div class="grid grid-cols-4 gap-3">
        <div>
            <label class="text-xs text-gray-400">Підʼїзд</label>
            <input type="text" wire:model.defer="entrance" class="poof-input w-full">
            @error('entrance')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="text-xs text-gray-400">Домофон</label>
            <input type="text" wire:model.defer="intercom" class="poof-input w-full">
            @error('intercom')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="text-xs text-gray-400">Поверх</label>
            <input type="text" wire:model.defer="floor" class="poof-input w-full">
            @error('floor')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="text-xs text-gray-400">Квартира</label>
            <input type="text" wire:model.defer="apartment" class="poof-input w-full">
            @error('apartment')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- ✅ Зберегти --}}
    <button
        type="submit"
        wire:loading.attr="disabled"
        wire:target="save"
        class="w-full bg-yellow-400 text-black font-bold py-3 rounded-2xl
               active:scale-95 transition disabled:opacity-70"
    >
        <span wire:loading.remove wire:target="save">Зберегти</span>
        <span wire:loading wire:target="save">Збереження…</span>
    </button>

</form>
