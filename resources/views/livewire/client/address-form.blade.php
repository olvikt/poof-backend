<form
    id="address-form"
    wire:submit.prevent="save"
    class="address-picker-screen h-full space-y-0 overflow-y-auto overflow-x-hidden pb-8"
    x-data="addressAutocomplete()"
    x-init="init()"
    @keydown.escape.window="if (isAddressSearchOpen) { closeAddressSearch() }"
>

    <section class="address-picker-hero -mt-px">
        <div class="address-picker-map-shell relative w-full overflow-hidden">
            <div class="address-picker-status-fade pointer-events-none absolute inset-x-0 top-0 z-[2] h-28"></div>

            <div class="address-picker-map-frame h-[45vh] min-h-[340px] w-full" wire:ignore>
                <div
                    id="map"
                    class="map-container absolute inset-x-0 bottom-0 w-full"
                ></div>
            </div>

            <div class="pointer-events-none absolute inset-0 z-[3] flex items-center justify-center">
                <div class="address-picker-center-marker" aria-hidden="true">
                    <img src="/images/logo-poof.svg" alt="" class="h-7 w-7">
                </div>
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
                :disabled="geoActionState === 'loading'"
                :class="geoActionState === 'loading' ? 'cursor-wait' : ''"
                class="address-picker-map-overlay address-picker-location-action absolute bottom-4 right-4 inline-flex items-center gap-2 rounded-full border border-black/5 bg-white px-4 py-3 text-sm font-semibold text-neutral-950 shadow-[0_16px_40px_-20px_rgba(15,23,42,0.45)] backdrop-blur-sm transition hover:bg-neutral-50 active:scale-95 disabled:pointer-events-none"
            >
                <svg aria-hidden="true" viewBox="0 0 20 20" class="h-6 w-6 text-black" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 2.5v2.2M10 15.3v2.2M17.5 10h-2.2M4.7 10H2.5" />
                    <circle cx="10" cy="10" r="3.2" />
                </svg>
                <span x-text="geoActionState === 'loading' ? 'Визначаємо…' : 'Моя локація'"></span>
            </button>

            <p
                x-cloak
                x-show="geoActionHint"
                x-text="geoActionHint"
                class="address-picker-map-overlay absolute bottom-[4.9rem] right-4 max-w-[min(18rem,calc(100%-2rem))] rounded-2xl bg-neutral-950/88 px-3 py-2 text-[11px] leading-4 text-neutral-100 shadow-lg backdrop-blur-sm"
            ></p>
        </div>
    </section>

    <section class="address-picker-bottom-sheet">
        <div class="address-picker-sheet-block space-y-5">
            <div class="space-y-3">
                <button
                    type="button"
                    wire:click="openAddressSearch"
                    x-on:click="openAddressSearch()"
                    class="relative w-full rounded-[1.75rem] border border-[#666666] bg-neutral-800/95 px-5 py-4 pr-16 text-left shadow-[0_24px_60px_-36px_rgba(0,0,0,0.9)] transition hover:border-[#7a7a7a] hover:bg-neutral-800"
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

            <div class="building-type-panel flex items-center justify-between gap-3 rounded-[1.15rem] px-1 py-1.5">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold leading-5 text-white">Приватний будинок</p>
                    <p class="mt-0.5 text-xs leading-4 text-neutral-400">Увімкніть якщо будинок приватний.</p>
                </div>

                <button
                    type="button"
                    role="switch"
                    aria-label="Приватний будинок"
                    aria-checked="{{ $building_type === 'house' ? 'true' : 'false' }}"
                    wire:click.prevent="toggleBuildingType"
                    class="building-type-switch relative inline-flex h-8 w-14 shrink-0 items-center rounded-full border transition focus:outline-none focus:ring-2 focus:ring-yellow-300/60 {{ $building_type === 'house' ? 'border-yellow-300 bg-yellow-400' : 'border-neutral-600 bg-neutral-700' }}"
                >
                    <span class="sr-only">Приватний будинок</span>
                    <span class="building-type-switch-thumb pointer-events-none inline-block h-6 w-6 rounded-full bg-white shadow-md transition-transform {{ $building_type === 'house' ? 'translate-x-7' : 'translate-x-1' }}"></span>
                </button>
            </div>

            <div class="flex items-start justify-between gap-4 border-b border-neutral-800/80 px-1 pb-4 pt-1">
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
        </div>

        @if($building_type === 'apartment')
            <section class="address-picker-section-stack address-picker-sheet-section space-y-4 px-4 pb-1">
                <div class="flex items-center justify-between gap-3">
                    <label class="block text-xs text-gray-400">Деталізація</label>
                    <span class="text-[11px] text-neutral-500">* Обов'язково для квартири</span>
                </div>

                <div class="address-detail-grid">
                    <div class="address-mini-input">
                        <x-poof.input-floating label="Підʼїзд *" model="entrance" center inputmode="numeric" pattern="[0-9]*" />
                        @error('entrance')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="address-mini-input">
                        <x-poof.input-floating label="Поверх *" model="floor" center inputmode="numeric" pattern="[0-9]*" />
                        @error('floor')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="address-mini-input">
                        <x-poof.input-floating label="Кв./офіс *" model="apartment" center inputmode="numeric" pattern="[0-9]*" />
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

        <section class="address-picker-section-stack address-picker-sheet-section space-y-5 px-4 pb-1">
            <div class="space-y-3">
                <label class="text-xs text-gray-400 mb-2 block">Тип адреси</label>

                <div class="grid grid-cols-3 gap-2.5 pt-1">
                    @foreach (['home' => 'Дім', 'work' => 'Робота', 'other' => 'Інше'] as $key => $text)
                        <button
                            type="button"
                            wire:click="$set('label','{{ $key }}')"
                            class="min-w-0 rounded-2xl px-3 py-2 text-center text-sm font-semibold transition sm:px-4
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
        </section>
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
                        x-show="shouldShowLocationBootstrapLoading()"
                        x-cloak
                        class="px-4 py-5 text-sm text-gray-300"
                    >
                        Визначаємо вашу локацію, щоб показати локально релевантні результати. Можна почати вводити адресу вже зараз — пошук поки що буде без локального зміщення.
                    </div>

                    <div
                        x-show="shouldShowCurrentMapPointSelection()"
                        x-cloak
                        class="px-2 pb-2"
                    >
                        <button
                            type="button"
                            x-on:click="selectSuggestion(currentMapPointSelection())"
                            class="flex w-full items-start gap-3 rounded-2xl px-4 py-4 text-left transition hover:bg-neutral-900"
                        >
                            <span class="mt-0.5 text-yellow-400">📍</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-white" x-text="currentMapPointSelection()?.line1 || currentMapPointSelection()?.label || ''"></span>
                                <span class="mt-1 block truncate text-xs text-neutral-400">Обрати адресу з карти</span>
                            </span>
                        </button>
                    </div>

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

    <style>
    #address-form {
        --address-sheet-bg: linear-gradient(180deg, rgba(24, 24, 27, 0.985) 0%, rgba(12, 12, 14, 0.99) 100%);
        --address-sheet-border: rgba(255, 255, 255, 0.05);
        --address-safe-top: max(env(safe-area-inset-top), 0px);
        position: relative;
        padding-top: 0;
        overscroll-behavior: contain;
    }

    #address-form .address-picker-hero {
        position: relative;
        margin-top: calc(var(--address-safe-top) * -1);
        padding-top: var(--address-safe-top);
    }

    #address-form .address-picker-map-shell {
        background:
            radial-gradient(circle at top center, rgba(247, 208, 96, 0.16) 0%, rgba(255, 255, 255, 0.06) 18%, rgba(255, 255, 255, 0) 46%),
            linear-gradient(180deg, rgba(20, 20, 24, 0.08) 0%, rgba(20, 20, 24, 0.03) 18%, rgba(20, 20, 24, 0) 38%);
        isolation: isolate;
    }

    #address-form .address-picker-map-frame {
        position: relative;
        min-height: calc(340px + var(--address-safe-top));
        height: calc(45vh + var(--address-safe-top));
        margin-top: calc(var(--address-safe-top) * -1);
        padding-top: var(--address-safe-top);
        overflow: hidden;
        box-sizing: border-box;
        background: linear-gradient(180deg, rgba(24, 25, 31, 0.82) 0%, rgba(24, 25, 31, 0.2) 22%, rgba(24, 25, 31, 0) 45%);
    }

    #address-form .address-picker-map-frame .map-container {
        top: calc(var(--address-safe-top) * -1);
        height: calc(100% + var(--address-safe-top));
        transform: translateZ(0);
    }

    #address-form .address-picker-map-overlay {
        z-index: 70;
    }

    #address-form .address-picker-status-fade {
        height: calc(5.75rem + var(--address-safe-top));
        background:
            linear-gradient(180deg, rgba(24, 25, 31, 0.12) 0%, rgba(24, 25, 31, 0.05) 36%, rgba(24, 25, 31, 0) 100%),
            radial-gradient(circle at top center, rgba(247, 208, 96, 0.05) 0%, rgba(247, 208, 96, 0.02) 28%, rgba(247, 208, 96, 0) 58%);
        mix-blend-mode: normal;
        opacity: 1;
    }

    #address-form .address-picker-map-shell .leaflet-control-container,
    #address-form .address-picker-map-shell .leaflet-pane,
    #address-form .address-picker-map-shell .leaflet-top,
    #address-form .address-picker-map-shell .leaflet-bottom {
        z-index: 1 !important;
    }

    #address-form .address-picker-section-stack,
    #address-form .address-picker-bottom-sheet {
        width: calc(100% - 0rem);
        margin-inline: 0rem;
    }

    #address-form .address-picker-bottom-sheet {
        position: relative;
        z-index: 5;
        margin-top: -2.6rem;
        padding: 0 0 1.25rem;
        border-radius: 1.75rem 1.75rem 0 0;
        background: var(--address-sheet-bg);
        border: 1px solid var(--address-sheet-border);
        box-shadow: 0 -14px 36px -28px rgba(0, 0, 0, 0.82), 0 22px 52px -38px rgba(0, 0, 0, 0.82);
        backdrop-filter: blur(12px);
        overflow: hidden;
    }

    #address-form .address-picker-sheet-block {
        padding: 0.85rem 1rem 0.35rem;
    }

    #address-form .address-picker-sheet-section {
        position: relative;
        z-index: 5;
        margin-top: 0;
        padding-top: 0.75rem;
        background: transparent;
        border-inline: 0;
    }

    #address-form .address-picker-sheet-section:last-of-type {
        padding-bottom: 1.2rem;
    }

    #address-form .address-picker-section-stack + .address-picker-section-stack {
        margin-top: 0;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        padding-top: 0.85rem;
    }

    #address-form .building-type-toggle {
        min-height: 4.1rem;
    }

    #address-form .building-type-panel {
        border: 0;
        background: transparent;
    }

    #address-form .building-type-switch {
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
    }

    #address-form .address-picker-center-marker {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 9999px;
        background: rgba(250, 204, 21, 0.96);
        box-shadow: 0 18px 35px -18px rgba(250, 204, 21, 0.95), 0 10px 18px -12px rgba(15, 23, 42, 0.6);
        transform: translateY(-1.15rem);
    }

    #address-form .address-picker-center-marker::after {
        content: '';
        position: absolute;
        left: 50%;
        bottom: -0.85rem;
        width: 0.95rem;
        height: 1.05rem;
        background: rgba(250, 204, 21, 0.96);
        clip-path: polygon(50% 100%, 0 0, 100% 0);
        transform: translateX(-50%);
        filter: drop-shadow(0 8px 12px rgba(15, 23, 42, 0.25));
    }

    #address-form .address-detail-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.75rem;
        align-items: start;
    }

    #address-form .address-mini-input {
        min-width: 0;
    }

    @media (max-width: 420px) {
        #address-form .address-picker-bottom-sheet {
            margin-top: -2.5rem;
        }

        #address-form .address-picker-sheet-block {
            padding-top: 0.85rem;
        }

        #address-form .address-picker-location-action {
            bottom: 5rem;
        }

        #address-form .address-detail-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    </style>
</form>
