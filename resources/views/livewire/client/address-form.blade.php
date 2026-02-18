<form wire:submit.prevent="save" class="space-y-5">

    {{-- =========================================================
     | Тип адреси
     ========================================================= --}}
    <div class="flex gap-2">
        @foreach (['home' => 'Дім', 'work' => 'Робота', 'other' => 'Інше'] as $key => $text)
            <button
                type="button"
                wire:click="$set('label','{{ $key }}')"
                class="px-4 py-2 rounded-xl text-sm font-semibold transition
                    {{ $label === $key
                        ? 'bg-yellow-400 text-black'
                        : 'bg-neutral-800 text-gray-300 hover:bg-neutral-700' }}"
            >
                {{ $text }}
            </button>
        @endforeach
    </div>

    {{-- =========================================================
     | Назва
     ========================================================= --}}
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

    {{-- =========================================================
     | MAP
     ========================================================= --}}
    <div>
        <x-poof.map>
            Уточніть точку адреси
        </x-poof.map>

        @if($lat && $lng)
            <p class="mt-2 text-xs text-green-400">✔ Точка підтверджена</p>
        @else
            <p class="mt-2 text-xs text-yellow-400">
                ⚠ Будь ласка, уточніть точку на мапі
            </p>
        @endif
    </div>

    {{-- =========================================================
     | Тип будівлі
     ========================================================= --}}
    <div>
        <label class="text-xs text-gray-400 mb-2 block">Тип будівлі</label>

        <div class="flex gap-2">
            <button
                type="button"
                wire:click="$set('building_type','apartment')"
                class="px-4 py-2 rounded-xl text-sm font-semibold transition
                    {{ $building_type === 'apartment'
                        ? 'bg-yellow-400 text-black'
                        : 'bg-neutral-800 text-gray-300 hover:bg-neutral-700' }}"
            >
                🏢 Квартира
            </button>

            <button
                type="button"
                wire:click="$set('building_type','house')"
                class="px-4 py-2 rounded-xl text-sm font-semibold transition
                    {{ $building_type === 'house'
                        ? 'bg-yellow-400 text-black'
                        : 'bg-neutral-800 text-gray-300 hover:bg-neutral-700' }}"
            >
                🏠 Приватний будинок
            </button>
        </div>
    </div>

    {{-- =========================================================
     | Адреса + будинок
     ========================================================= --}}
    <div class="relative">
        <label class="text-xs text-gray-400">Адреса</label>

        <div class="flex gap-2">
            {{-- Адреса --}}
            <div class="relative flex-1">
                <input
                    type="text"
                    wire:model.live="search"
                    wire:keydown.enter.prevent
                    placeholder="Вулиця, район…"
                    class="poof-input w-full"
                >

                @if (!empty($suggestions))
                    <div class="absolute z-50 mt-1 w-full rounded-xl bg-neutral-900 border border-neutral-700 shadow-xl">
                        @foreach ($suggestions as $item)
                            <button
                                type="button"
                                wire:click="selectPlace('{{ $item['place_id'] }}')"
                                class="block w-full text-left px-4 py-2 text-sm hover:bg-neutral-800"
                            >
                                {{ $item['label'] }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Будинок --}}
            <div class="w-20">
                <input
                    type="text"
                    wire:model.live.debounce.700ms="house"
                    placeholder="Буд."
                    class="poof-input w-full text-center"
                >
            </div>
        </div>

        @error('search')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror

        @error('house')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- =========================================================
     | Додаткові поля (КВАРТИРА)
     ========================================================= --}}
    @if($building_type === 'apartment')
        <div class="grid grid-cols-4 gap-3">
            <input wire:model.defer="entrance" placeholder="Підʼїзд" class="poof-input">
            <input wire:model.defer="intercom" placeholder="Домофон" class="poof-input">
            <input wire:model.defer="floor" placeholder="Поверх" class="poof-input">
            <input wire:model.defer="apartment" placeholder="Квартира" class="poof-input">
        </div>
    @endif

    {{-- =========================================================
     | SAVE
     ========================================================= --}}
    <button
        type="submit"
        wire:loading.attr="disabled"
        class="w-full bg-yellow-400 text-black font-bold py-3 rounded-2xl
               active:scale-95 transition disabled:opacity-70"
    >
        <span wire:loading.remove>Зберегти</span>
        <span wire:loading>Збереження…</span>
    </button>

</form>
