<form
    id="address-form"
    wire:submit.prevent="save"
    class="space-y-5"
    x-data="addressAutocomplete()"
    x-init="init()"
    @keydown.escape.window="if (isAddressSearchOpen) { closeAddressSearch() }"
>

    <div class="space-y-3 rounded-2xl bg-neutral-900/40 p-3">
        <div class="w-full h-[320px] rounded-2xl overflow-hidden border border-neutral-700 bg-neutral-800">
            <div class="relative w-full h-full" wire:ignore>
                <div
                    id="map"
                    class="map-container w-full h-full"
                ></div>

                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div class="text-yellow-400 text-3xl drop-shadow-lg">📍</div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between mt-3 gap-3">
            <span class="text-sm text-neutral-300">Точка адреси</span>

            <button
                type="button"
                id="use-location-btn"
                class="inline-flex items-center gap-2 font-semibold rounded-xl px-3 py-1.5 bg-yellow-400 text-black active:scale-95"
            >
                📍 Моя локація
            </button>
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

    <div class="space-y-2">
        <label class="text-xs text-gray-400 block">Адреса</label>

        <button
            type="button"
            wire:click="openAddressSearch"
            x-on:click="openAddressSearch()"
            class="w-full rounded-3xl border border-neutral-700 bg-neutral-900/60 px-4 py-4 text-left shadow-sm transition hover:border-neutral-500 hover:bg-neutral-900"
            data-address-search-trigger
        >
            <div class="flex items-start gap-3">
                <span class="mt-0.5 text-lg text-yellow-400">🔎</span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs uppercase tracking-[0.24em] text-neutral-500">Пошук адреси</p>
                    @if(filled($search))
                        <p class="mt-1 truncate text-sm font-medium text-white">{{ $search }}</p>
                        <p class="mt-1 text-xs text-neutral-400">
                            {{ collect([$street ? trim($street . ' ' . $house) : null, $city, $region])->filter()->join(' • ') }}
                        </p>
                    @else
                        <p class="mt-1 text-sm text-neutral-300">Введіть адресу, будинок або виберіть точку на мапі</p>
                        <p class="mt-1 text-xs text-neutral-500">Один пошук замість окремих полів вулиці, міста та області</p>
                    @endif
                </div>
                <span class="mt-1 text-neutral-500">›</span>
            </div>
        </button>

        @error('search')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror

        @error('street')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror

        @error('house')
            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
        @enderror
    </div>

    <input type="hidden" wire:model.live="search" data-address-search>
    <input type="hidden" wire:model.live="street" data-address-street>
    <input type="hidden" wire:model.live="house" data-address-house>
    <input type="hidden" wire:model.live="city" data-address-city>
    <input type="hidden" wire:model.live="region" data-address-region>

    <div
        x-cloak
        x-show="isAddressSearchOpen"
        x-transition.opacity
        class="fixed inset-0 z-[70] bg-neutral-950/95 backdrop-blur-sm"
        data-address-search-modal
    >
        <div class="flex h-full flex-col px-4 pb-4 pt-[max(env(safe-area-inset-top),1rem)]">
            <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col overflow-hidden rounded-[2rem] border border-neutral-800 bg-neutral-950 shadow-2xl">
                <div class="border-b border-neutral-800 px-4 pb-4 pt-4">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-neutral-900 text-xl text-neutral-200"
                            wire:click="closeAddressSearch"
                            x-on:click="closeAddressSearch()"
                            aria-label="Закрити пошук адреси"
                        >
                            ←
                        </button>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-white">Оберіть адресу</p>
                            <p class="text-xs text-neutral-400">Bolt / Uber style fullscreen пошук з автодоповненням</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-2xl border border-neutral-700 bg-neutral-900 px-4 py-3">
                        <span class="text-lg text-yellow-400">🔎</span>
                        <input
                            type="text"
                            x-model="search"
                            x-ref="addressSearchInput"
                            wire:keydown.arrow-down.prevent="moveSuggestionDown"
                            wire:keydown.arrow-up.prevent="moveSuggestionUp"
                            wire:keydown.enter.prevent="selectActiveSuggestion"
                            placeholder="Вулиця, будинок, район…"
                            class="w-full bg-transparent text-base text-white outline-none placeholder:text-neutral-500"
                            autocomplete="off"
                        >
                        <button
                            type="button"
                            x-show="search"
                            x-cloak
                            x-on:click="clearSearch()"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-neutral-800 text-sm text-neutral-200"
                            aria-label="Очистити пошук"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-2 py-2">
                    <div x-show="isLoadingSuggestions" class="px-4 py-4 text-sm text-gray-300">Пошук адреси…</div>

                    <div
                        x-show="shouldShowRecent()"
                        x-cloak
                        class="px-2 py-2"
                    >
                        <div class="mb-3 flex items-center justify-between gap-3 px-2">
                            <div>
                                <p class="text-sm font-semibold text-white">Нещодавні адреси</p>
                                <p class="text-xs text-neutral-400">Швидко оберіть одну з раніше підтверджених адрес.</p>
                            </div>
                            <button
                                type="button"
                                x-show="recentAddresses.length"
                                x-on:click="clearRecentAddresses()"
                                class="rounded-full bg-neutral-900 px-3 py-1.5 text-xs font-medium text-neutral-300 transition hover:bg-neutral-800"
                            >
                                Очистити
                            </button>
                        </div>

                        <ul class="space-y-1">
                            <template x-for="(item, index) in recentAddresses" :key="'recent-' + item.lat + '-' + item.lng + '-' + index">
                                <li>
                                    <button
                                        type="button"
                                        @mousedown.prevent="selectSuggestion(item)"
                                        class="flex w-full items-start gap-3 rounded-2xl px-4 py-4 text-left transition hover:bg-neutral-900"
                                    >
                                        <span class="mt-0.5 text-yellow-400">🕘</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-semibold text-white" x-text="item.line1 || item.label || ''"></span>
                                            <span class="mt-1 block truncate text-xs text-neutral-400" x-text="item.line2 || item.city || ''"></span>
                                        </span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <template x-if="!isLoadingSuggestions && !shouldShowRecent() && suggestions.length">
                        <ul class="space-y-1">
                            <template x-for="(item, index) in suggestions" :key="item.lat + '-' + item.lng + '-' + index">
                                <li>
                                    <button
                                        type="button"
                                        @mousedown.prevent="selectSuggestion(item)"
                                        class="flex w-full items-start gap-3 rounded-2xl px-4 py-4 text-left transition hover:bg-neutral-900"
                                        :class="index === $wire.activeSuggestionIndex ? 'bg-neutral-900' : ''"
                                    >
                                        <span class="mt-0.5 text-yellow-400">📍</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-semibold text-white" x-html="highlight(item.line1 || item.label || '')"></span>
                                            <span class="mt-1 block truncate text-xs text-neutral-400" x-html="highlight(item.line2 || item.city || '')"></span>
                                        </span>
                                        <span x-show="item.distance" class="text-xs text-neutral-500" x-text="item.distance"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </template>

                    <div
                        x-show="!isLoadingSuggestions && !shouldShowRecent() && !suggestions.length && Boolean(suggestionsMessage)"
                        class="px-4 py-4 text-sm text-gray-300"
                        x-text="typeof suggestionsMessage === 'string' ? suggestionsMessage : ''"
                    ></div>

                    <div x-show="!isLoadingSuggestions && !shouldShowRecent() && !suggestions.length && !suggestionsMessage" class="px-4 py-8 text-center text-sm text-neutral-500">
                        Почніть вводити адресу, щоб побачити підказки.
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($building_type === 'apartment')
        <div class="flex gap-2 mt-3">
            <div class="address-mini-input">
                <input
                    type="text"
                    wire:model.defer="entrance"
                    placeholder="Підʼїзд"
                    class="poof-input w-full text-center"
                >
                @error('entrance')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="address-mini-input">
                <input
                    type="text"
                    wire:model.defer="floor"
                    placeholder="Поверх"
                    class="poof-input w-full text-center"
                >
                @error('floor')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="address-mini-input">
                <input
                    type="text"
                    wire:model.defer="apartment"
                    placeholder="Кв./офіс"
                    class="poof-input w-full text-center"
                >
                @error('apartment')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="address-mini-input">
                <input
                    type="text"
                    wire:model.defer="intercom"
                    placeholder="Домофон"
                    class="poof-input w-full text-center"
                >
                @error('intercom')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>
    @endif

</form>

<style>
    .address-mini-input {
        width: 72px;
    }
</style>
