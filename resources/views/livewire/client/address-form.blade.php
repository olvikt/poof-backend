<form wire:submit.prevent="save" class="space-y-5"
    x-data="{
        search: $wire.entangle('search').live,
        lat: $wire.entangle('lat'),
        lng: $wire.entangle('lng'),
        street: $wire.entangle('street'),
        house: $wire.entangle('house'),
        city: $wire.entangle('city'),
        debounceTimer: null,
        abortController: null,
        requestId: 0,
        isLoadingSuggestions: false,
        init() {
            this.$watch('search', (value) => {
                const query = String(value ?? '').trim();

                if (this.debounceTimer) {
                    clearTimeout(this.debounceTimer);
                }

                if (this.abortController) {
                    this.abortController.abort();
                    this.abortController = null;
                }

                if (query.length < 3) {
                    this.isLoadingSuggestions = false;
                    this.$wire.call('setPhotonSuggestions', [], null);
                    return;
                }

                this.debounceTimer = setTimeout(() => this.fetchSuggestions(query), 300);
            });
        },
        async fetchSuggestions(query) {
            const currentRequestId = ++this.requestId;
            this.abortController = new AbortController();
            this.isLoadingSuggestions = true;

            const { lat, lng } = this.getBiasCoordinates();
            const params = new URLSearchParams({ q: query });

            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                params.set('lat', lat.toFixed(6));
                params.set('lng', lng.toFixed(6));
            }

            try {
                const response = await fetch(`/api/geocode?${params.toString()}`, {
                    signal: this.abortController.signal,
                });

                if (currentRequestId !== this.requestId) {
                    return;
                }

                if (!response.ok) {
                    this.$wire.call('setPhotonSuggestions', [], 'Адресу не знайдено');
                    return;
                }

                const items = await response.json();
                this.$wire.call('setPhotonSuggestions', items, items.length ? null : 'Адресу не знайдено');
            } catch (error) {
                if (error?.name !== 'AbortError' && currentRequestId === this.requestId) {
                    this.$wire.call('setPhotonSuggestions', [], 'Адресу не знайдено');
                }
            } finally {
                if (currentRequestId === this.requestId) {
                    this.isLoadingSuggestions = false;
                }
            }
        },
        getBiasCoordinates() {
            const fallback = { lat: 48.450000, lng: 34.980000 };
            const mapCenter = window.POOF?.map?.instance?.getCenter?.();

            if (mapCenter && Number.isFinite(mapCenter.lat) && Number.isFinite(mapCenter.lng)) {
                return {
                    lat: Number(mapCenter.lat.toFixed(6)),
                    lng: Number(mapCenter.lng.toFixed(6)),
                };
            }

            if (Number.isFinite(this.lat) && Number.isFinite(this.lng)) {
                return {
                    lat: Number(this.lat.toFixed(6)),
                    lng: Number(this.lng.toFixed(6)),
                };
            }

            return fallback;
        },
        escapeRegExp(value) {
            return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },
        highlight(text) {
            const source = String(text ?? '');
            const query = String(this.search ?? '').trim();

            if (!query || source === '') {
                return source;
            }

            const regex = new RegExp(`(${this.escapeRegExp(query)})`, 'ig');
            return source.replace(regex, '<mark class="bg-yellow-400/30 text-yellow-200 rounded px-0.5">$1</mark>');
        }
    }">

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

    <div>
        <x-poof.map>
            Уточніть точку адреси
        </x-poof.map>

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
                >

                <div
                    x-cloak
                    x-show="isLoadingSuggestions || {{ !empty($suggestions) ? 'true' : 'false' }} || {{ !empty($suggestionsMessage) ? 'true' : 'false' }}"
                    class="absolute z-50 mt-2 w-full overflow-hidden rounded-xl border border-neutral-700 bg-neutral-900 shadow-xl"
                >
                    <div x-show="isLoadingSuggestions" class="px-4 py-3 text-sm text-gray-300">Пошук адреси…</div>

                    @if (!empty($suggestions))
                        @foreach ($suggestions as $item)
                            <button
                                type="button"
                                wire:click="selectSuggestion({{ $loop->index }})"
                                class="flex w-full items-start gap-3 px-4 py-3 text-left text-sm transition {{ $activeSuggestionIndex === $loop->index ? 'bg-neutral-800' : 'hover:bg-neutral-800' }}"
                            >
                                <span class="text-yellow-400">📍</span>
                                <span class="min-w-0">
                                    <span class="block font-medium text-gray-100" x-html="highlight(@js($item['line1'] ?? $item['label']))"></span>
                                    @if(!empty($item['line2']))
                                        <span class="block text-xs text-gray-400" x-html="highlight(@js($item['line2']))"></span>
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    @elseif (!empty($suggestionsMessage))
                        <div x-show="!isLoadingSuggestions" class="px-4 py-3 text-sm text-gray-300">
                            {{ $suggestionsMessage }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="w-20">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="house"
                    placeholder="Буд."
                    class="poof-input w-full text-center"
                >
            </div>
        </div>

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

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="text-xs text-gray-400">Місто</label>
            <input type="text" wire:model.live.debounce.300ms="city" placeholder="Місто" class="poof-input w-full">
            @error('city')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="text-xs text-gray-400">Область</label>
            <input type="text" wire:model.live.debounce.300ms="region" placeholder="Область" class="poof-input w-full">
            @error('region')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @if($building_type === 'apartment')
        <div class="grid grid-cols-4 gap-3">
            <div>
                <input wire:model.defer="entrance" placeholder="Підʼїзд" class="poof-input">
                @error('entrance')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <input wire:model.defer="intercom" placeholder="Домофон" class="poof-input">
                @error('intercom')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <input wire:model.defer="floor" placeholder="Поверх" class="poof-input">
                @error('floor')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <input wire:model.defer="apartment" placeholder="Квартира" class="poof-input">
                @error('apartment')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>
    @endif

    <button
        type="submit"
        wire:loading.attr="disabled"
        x-bind:disabled="!lat || !lng || !street || !house || !city || !String(street).trim() || !String(house).trim() || !String(city).trim()"
        class="w-full bg-yellow-400 text-black font-bold py-3 rounded-2xl
               active:scale-95 transition disabled:opacity-70"
    >
        <span wire:loading.remove>Зберегти</span>
        <span wire:loading>Збереження…</span>
    </button>

</form>
