<form
    wire:submit.prevent="save"
    class="space-y-5"
    x-data="addressAutocomplete()"
    x-init="init()"
>

    <div class="space-y-3 rounded-2xl bg-neutral-900/40 p-4">
        <div class="w-full h-[320px] rounded-2xl overflow-hidden border border-neutral-700 bg-neutral-800">
            <div
                id="map"
                wire:ignore
                class="w-full h-full"
            ></div>
        </div>

        <h3 class="text-sm text-gray-300">Уточніть точку адреси</h3>

        <div class="flex justify-end">
            <x-poof.button
                type="button"
                id="use-location-btn"
                size="sm"
            >
                📍 Моя локація
            </x-poof.button>
        </div>

        <div class="flex gap-2 pt-1">
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

        @if($label === 'other')
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
        @endif

        @if($lat && $lng)
            <p class="mt-2 text-xs text-green-400">✔ Точка підтверджена</p>
        @else
            <p class="mt-2 text-xs text-yellow-400">⚠ Будь ласка, уточніть точку на мапі</p>
        @endif

        @error('lat')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror

        @error('lng')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror
    </div>

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

    <div class="relative">
        <label class="text-xs text-gray-400">Адреса</label>

        <div class="flex gap-2">
            <div class="relative flex-1">
                <input
                    type="text"
                    x-model="search"
                    wire:keydown.arrow-down.prevent="moveSuggestionDown"
                    wire:keydown.arrow-up.prevent="moveSuggestionUp"
                    wire:keydown.enter.prevent="selectActiveSuggestion"
                    placeholder="Вулиця, район…"
                    class="poof-input w-full"
                    autocomplete="off"
                    data-address-search
                >

                <div
                    x-cloak
                    x-show="isLoadingSuggestions || suggestions.length > 0 || Boolean(suggestionsMessage)"
                    class="absolute z-50 mt-2 w-full overflow-hidden rounded-xl border border-neutral-700 bg-neutral-900 shadow-xl"
                >
                    <div x-show="isLoadingSuggestions" class="px-4 py-3 text-sm text-gray-300">Пошук адреси…</div>

                    <ul x-show="suggestions.length" x-cloak>
                        <template x-for="(item, index) in suggestions" :key="item.lat + '-' + item.lng + '-' + index">
                            <li @mousedown.prevent="selectSuggestion(item)" class="flex cursor-pointer items-start gap-3 px-4 py-3 text-left text-sm transition hover:bg-neutral-800">
                                <span class="text-yellow-400">📍</span>
                                <span class="min-w-0">
                                    <span class="block font-medium text-gray-100" x-html="highlight(item.label || item.line1 || '')"></span>
                                    <span x-show="item.line2" class="block text-xs text-gray-400" x-html="highlight(item.line2)"></span>
                                </span>
                            </li>
                        </template>
                    </ul>

                    <div x-show="!isLoadingSuggestions && !suggestions.length && Boolean(suggestionsMessage)" class="px-4 py-3 text-sm text-gray-300" x-text="typeof suggestionsMessage === 'string' ? suggestionsMessage : ''"></div>
                </div>
            </div>

            <div class="w-20">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="house"
                    placeholder="Буд."
                    class="poof-input w-full text-center"
                    data-address-house
                >
            </div>
        </div>

        @error('search')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror

        @error('street')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror

        <input type="hidden" wire:model.live="street" data-address-street>

        @error('house')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="text-xs text-gray-400">Місто</label>
            <input type="text" wire:model.live.debounce.300ms="city" placeholder="Місто" class="poof-input w-full" data-address-city>
            @error('city')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="text-xs text-gray-400">Область</label>
            <input type="text" wire:model.live.debounce.300ms="region" placeholder="Область" class="poof-input w-full" data-address-region>
            @error('region')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @if($building_type === 'apartment')
        <div class="grid grid-cols-2 gap-3 mt-3">
            <div class="relative">
                <input
                    type="text"
                    wire:model.defer="entrance"
                    placeholder=" "
                    class="peer w-full rounded-xl border border-neutral-700 bg-neutral-900 px-3 pt-4 pb-1 text-sm text-white focus:outline-none focus:border-yellow-400"
                >
                <label
                    class="absolute left-3 top-2 text-xs text-neutral-400 transition-all
                        peer-placeholder-shown:top-3
                        peer-placeholder-shown:text-sm
                        peer-placeholder-shown:text-neutral-500
                        peer-focus:top-1
                        peer-focus:text-xs
                        peer-focus:text-yellow-400"
                >
                    Підʼїзд
                </label>
                @error('entrance')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="relative">
                <input
                    type="text"
                    wire:model.defer="intercom"
                    placeholder=" "
                    class="peer w-full rounded-xl border border-neutral-700 bg-neutral-900 px-3 pt-4 pb-1 text-sm text-white focus:outline-none focus:border-yellow-400"
                >
                <label
                    class="absolute left-3 top-2 text-xs text-neutral-400 transition-all
                        peer-placeholder-shown:top-3
                        peer-placeholder-shown:text-sm
                        peer-placeholder-shown:text-neutral-500
                        peer-focus:top-1
                        peer-focus:text-xs
                        peer-focus:text-yellow-400"
                >
                    Домофон
                </label>
                @error('intercom')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="relative">
                <input
                    type="text"
                    wire:model.defer="floor"
                    placeholder=" "
                    class="peer w-full rounded-xl border border-neutral-700 bg-neutral-900 px-3 pt-4 pb-1 text-sm text-white focus:outline-none focus:border-yellow-400"
                >
                <label
                    class="absolute left-3 top-2 text-xs text-neutral-400 transition-all
                        peer-placeholder-shown:top-3
                        peer-placeholder-shown:text-sm
                        peer-placeholder-shown:text-neutral-500
                        peer-focus:top-1
                        peer-focus:text-xs
                        peer-focus:text-yellow-400"
                >
                    Поверх
                </label>
                @error('floor')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="relative">
                <input
                    type="text"
                    wire:model.defer="apartment"
                    placeholder=" "
                    class="peer w-full rounded-xl border border-neutral-700 bg-neutral-900 px-3 pt-4 pb-1 text-sm text-white focus:outline-none focus:border-yellow-400"
                >
                <label
                    class="absolute left-3 top-2 text-xs text-neutral-400 transition-all
                        peer-placeholder-shown:top-3
                        peer-placeholder-shown:text-sm
                        peer-placeholder-shown:text-neutral-500
                        peer-focus:top-1
                        peer-focus:text-xs
                        peer-focus:text-yellow-400"
                >
                    Квартира
                </label>
                @error('apartment')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>
    @endif

    <button
        type="submit"
        wire:loading.attr="disabled"
        x-bind:disabled="
            !lat ||
            !lng ||
            !street ||
            !house ||
            !city ||
            !String(street || '').trim() ||
            !String(house || '').trim() ||
            !String(city || '').trim()
        "
        class="w-full bg-yellow-400 text-black font-bold py-3 rounded-2xl
               active:scale-95 transition disabled:opacity-70"
    >
        <span wire:loading.remove>Зберегти</span>
        <span wire:loading>Збереження…</span>
    </button>

</form>
