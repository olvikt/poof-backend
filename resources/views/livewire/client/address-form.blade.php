<form
    id="address-form"
    wire:submit.prevent="save"
    class="address-picker-screen space-y-0 pb-6"
    x-data="addressAutocomplete()"
    x-init="init()"
    @keydown.escape.window="if (isAddressSearchOpen) { closeAddressSearch() }"
>

    <section class="address-picker-hero -mt-px">
        <div class="address-picker-map-shell relative w-full overflow-hidden bg-neutral-950">
            <div class="address-picker-status-fade pointer-events-none absolute inset-x-0 top-0 z-[2] h-24"></div>

            <div class="h-[45vh] min-h-[340px] w-full" wire:ignore>
                <div
                    id="map"
                    class="map-container h-full w-full"
                ></div>
            </div>

            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                <div class="text-4xl text-yellow-400 drop-shadow-lg">📍</div>
            </div>

            <button
                type="button"
                x-on:click="$dispatch('sheet:close', { name: 'addressForm' })"
                class="address-picker-map-overlay absolute left-4 top-[max(env(safe-area-inset-top),1rem)] inline-flex h-12 w-12 items-center justify-center rounded-full border border-black/5 bg-white text-neutral-950 shadow-[0_16px_40px_-20px_rgba(15,23,42,0.45)] backdrop-blur-sm transition hover:bg-neutral-50"
                aria-label="Назад"
            >
                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
            </button>

            <button
                type="button"
                id="use-location-btn"
                class="address-picker-map-overlay absolute bottom-28 right-4 inline-flex items-center gap-2 rounded-full border border-black/10 bg-white/95 px-4 py-3 text-sm font-semibold text-neutral-950 shadow-[0_18px_45px_-24px_rgba(15,23,42,0.5)] backdrop-blur-md transition active:scale-95 sm:bottom-32"
            >
                <svg aria-hidden="true" viewBox="0 0 20 20" class="h-4 w-4 text-neutral-950" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 2.5v2.2M10 15.3v2.2M17.5 10h-2.2M4.7 10H2.5" />
                    <circle cx="10" cy="10" r="3.2" />
                </svg>
                <span>Моя локація</span>
            </button>
        </div>
    </section>

    <section class="address-picker-bottom-sheet address-picker-section-stack space-y-5">
        <div class="flex items-start justify-between gap-4 border-b border-white/8 px-1 pb-4 pt-1">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400">Адреса</p>
            </div>
            <div class="text-right">
                @if($lat && $lng)
                    <p class="text-xs font-medium text-green-400">Точка підтверджена</p>
                @else
                    <p class="text-xs font-medium text-yellow-400">Будь ласка, уточніть точку на мапі</p>
                @endif
            </div>
        </div>

        @error('lat')
            <p class="text-xs text-red-400">{{ $message }}</p>
        @enderror

        @error('lng')
            <p class="text-xs text-red-400">{{ $message }}</p>
        @enderror

        <div class="space-y-3">
            <button
                type="button"
                wire:click="openAddressSearch"
                x-on:click="openAddressSearch()"
                class="relative w-full rounded-[1.75rem] border border-[#666666] bg-neutral-800/95 px-4 py-4 pr-14 text-left shadow-[0_24px_60px_-36px_rgba(0,0,0,0.9)] transition hover:border-neutral-500 hover:bg-neutral-700/90"
                data-address-search-trigger
            >
                <span class="absolute right-4 top-1/2 inline-flex -translate-y-1/2 items-center justify-center text-neutral-200">
                    <svg aria-hidden="true" viewBox="0 0 20 20" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="8.5" cy="8.5" r="4.75" />
                        <path d="M12 12l4.25 4.25" />
                    </svg>
                </span>

                <div class="min-w-0 pr-2">
                    @if(filled($search))
                        <p class="truncate text-[15px] font-semibold text-white">{{ $search }}</p>
                        <p class="mt-1 truncate text-xs text-neutral-400">
                            {{ collect([$street ? trim($street . ' ' . $house) : null, $city, $region])->filter()->join(' • ') }}
                        </p>
                    @else
                        <p class="pr-6 text-[15px] font-semibold text-white">Введіть адресу, будинок або виберіть точку на мапі</p>
                        <p class="mt-1 text-xs text-neutral-400">Пошук адреси</p>
                    @endif
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
    </section>

    <section class="address-picker-section-stack address-picker-sheet-section space-y-5 px-1 pb-1">
        <div class="space-y-2">
            <label class="text-xs text-gray-400 mb-2 block">Тип адреси</label>

            <div class="grid grid-cols-3 gap-2 pt-1">
                @foreach (['home' => 'Дім', 'work' => 'Робота', 'other' => 'Інше'] as $key => $text)
                    <button
                        type="button"
                        wire:click="$set('label','{{ $key }}')"
                        class="min-w-0 rounded-2xl px-3 py-3 text-center text-sm font-semibold transition sm:px-4
                            {{ $label === $key
                                ? 'bg-yellow-400 text-black shadow-[0_14px_30px_-18px_rgba(250,204,21,0.9)]'
                                : 'bg-neutral-800/95 text-gray-300 hover:bg-neutral-700' }}"
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
        </div>

        <div>
            <label class="text-xs text-gray-400 mb-2 block">Тип будівлі</label>

            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    wire:click="$set('building_type','apartment')"
                    class="min-w-0 rounded-2xl px-3 py-3 text-center text-sm font-semibold leading-tight transition sm:px-4
                        {{ $building_type === 'apartment'
                            ? 'bg-yellow-400 text-black shadow-[0_14px_30px_-18px_rgba(250,204,21,0.9)]'
                            : 'bg-neutral-800/95 text-gray-300 hover:bg-neutral-700' }}"
                >
                    🏢 Квартира
                </button>

                <button
                    type="button"
                    wire:click="$set('building_type','house')"
                    class="min-w-0 rounded-2xl px-3 py-3 text-center text-sm font-semibold leading-tight transition sm:px-4
                        {{ $building_type === 'house'
                            ? 'bg-yellow-400 text-black shadow-[0_14px_30px_-18px_rgba(250,204,21,0.9)]'
                            : 'bg-neutral-800/95 text-gray-300 hover:bg-neutral-700' }}"
                >
                    🏠 Приватний будинок
                </button>
            </div>
        </div>
    </section>

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
                        x-show="shouldShowCurrentLocationAction()"
                        x-cloak
                        class="px-2 pb-2"
                    >
                        <button
                            type="button"
                            x-on:click="selectCurrentLocation()"
                            class="flex w-full items-start gap-3 rounded-2xl px-4 py-4 text-left transition hover:bg-neutral-900"
                        >
                            <span class="mt-0.5 text-yellow-400">📡</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-white">Поточна локація на мапі</span>
                                <span class="mt-1 block truncate text-xs text-neutral-400">Окремо підставити адресу для поточного центру мапи.</span>
                            </span>
                        </button>
                    </div>

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
        <section class="address-picker-section-stack address-picker-sheet-section space-y-3 px-1 pb-1">
            <label class="text-xs text-gray-400 block">Деталізація</label>

            <div class="address-detail-grid mt-3">
                <div class="address-mini-input">
                    <x-poof.input-floating label="Підʼїзд" model="entrance" center inputmode="numeric" pattern="[0-9]*" />
                    @error('entrance')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="address-mini-input">
                    <x-poof.input-floating label="Поверх" model="floor" center inputmode="numeric" pattern="[0-9]*" />
                    @error('floor')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="address-mini-input">
                    <x-poof.input-floating label="Кв./офіс" model="apartment" center inputmode="numeric" pattern="[0-9]*" />
                    @error('apartment')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="address-mini-input">
                    <x-poof.input-floating label="Домофон" model="intercom" center inputmode="numeric" pattern="[0-9]*" />
                    @error('intercom')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>
    @endif

</form>

<style>
    #address-form {
        --address-sheet-bg: linear-gradient(180deg, rgba(24, 24, 27, 0.98) 0%, rgba(10, 10, 10, 0.98) 100%);
        position: relative;
    }

    #address-form .address-picker-map-overlay {
        z-index: 1200;
    }

    #address-form .address-picker-status-fade {
        background: linear-gradient(180deg, rgba(10, 10, 10, 0.5) 0%, rgba(10, 10, 10, 0.18) 38%, rgba(10, 10, 10, 0) 100%);
    }

    #address-form .address-picker-map-shell .leaflet-control-container,
    #address-form .address-picker-map-shell .leaflet-pane,
    #address-form .address-picker-map-shell .leaflet-top,
    #address-form .address-picker-map-shell .leaflet-bottom {
        z-index: 1 !important;
    }

    #address-form .address-picker-section-stack {
        width: calc(100% - 2rem);
        margin-inline: 1rem;
    }

    #address-form .address-picker-bottom-sheet {
        position: relative;
        z-index: 5;
        margin-top: -2.75rem;
        padding: 1.35rem 1rem 0.35rem;
        border-radius: 2rem 2rem 0 0;
        background: var(--address-sheet-bg);
        border: 1px solid rgba(255, 255, 255, 0.06);
        box-shadow: 0 -12px 40px -24px rgba(0, 0, 0, 0.8);
    }

    #address-form .address-picker-sheet-section {
        position: relative;
        z-index: 5;
        margin-top: 0;
        padding-top: 0.4rem;
        background: var(--address-sheet-bg);
        border-inline: 1px solid rgba(255, 255, 255, 0.06);
    }

    #address-form .address-picker-sheet-section:last-of-type {
        padding-bottom: 1.25rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 0 0 2rem 2rem;
    }

    #address-form .address-picker-section-stack + .address-picker-section-stack {
        margin-top: 0;
    }

    #address-form .address-detail-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.65rem;
    }

    #address-form .address-mini-input {
        min-width: 0;
    }

    @media (max-width: 420px) {
        #address-form .address-picker-bottom-sheet {
            margin-top: -2.25rem;
            padding-top: 1.15rem;
        }

        #address-form .address-detail-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>
