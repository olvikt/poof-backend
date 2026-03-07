<form wire:submit.prevent="save" class="space-y-5"
    x-data="{
        search: $wire.entangle('search').live,
        lat: $wire.entangle('lat'),
        lng: $wire.entangle('lng'),
        street: $wire.entangle('street'),
        house: $wire.entangle('house'),
        city: $wire.entangle('city'),
        photonDebounceTimer: null,
        photonAbortController: null,
        photonRequestId: 0,
        init() {
            this.$watch('search', (value) => {
                const query = String(value ?? '').trim();

                if (this.photonDebounceTimer) {
                    clearTimeout(this.photonDebounceTimer);
                }

                if (this.photonAbortController) {
                    this.photonAbortController.abort();
                    this.photonAbortController = null;
                }

                if (query.length < 3) {
                    this.$wire.call('setPhotonSuggestions', [], null);
                    return;
                }

                this.photonDebounceTimer = setTimeout(() => this.fetchPhoton(query), 300);
            });
        },
        async fetchPhoton(query) {
            const requestId = ++this.photonRequestId;
            this.photonAbortController = new AbortController();
            const { lat, lon } = this.getPhotonBiasCoordinates();

            try {
                const response = await fetch(`https://photon.komoot.io/api/?q=${encodeURIComponent(query)}&limit=5&lang=uk&lat=${lat}&lon=${lon}`, {
                    signal: this.photonAbortController.signal,
                });

                if (!response.ok) {
                    if (requestId === this.photonRequestId) {
                        this.$wire.call('setPhotonSuggestions', [], 'Адресу не знайдено');
                    }
                    return;
                }

                const payload = await response.json();
                const features = Array.isArray(payload?.features) ? payload.features : [];

                const items = features
                    .map((feature) => {
                        const properties = feature?.properties ?? {};
                        const coordinates = feature?.geometry?.coordinates;

                        if (!Array.isArray(coordinates) || coordinates.length < 2) {
                            return null;
                        }

                        const name = String(properties?.name ?? '').trim();
                        const city = String(properties?.city ?? '').trim();
                        const region = String(properties?.state ?? '').trim();

                        if (!name) {
                            return null;
                        }

                        return {
                            street: name,
                            city: city || null,
                            region: region || null,
                            lat: Number(coordinates[1]),
                            lng: Number(coordinates[0]),
                            line1: name,
                            line2: city ? `${city}${region ? `, ${region}` : ''}` : region,
                            label: [name, city].filter(Boolean).join(', '),
                        };
                    })
                    .filter((item) => item && Number.isFinite(item.lat) && Number.isFinite(item.lng));

                if (requestId !== this.photonRequestId) {
                    return;
                }

                this.$wire.call('setPhotonSuggestions', items, items.length ? null : 'Адресу не знайдено');
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }

                if (requestId === this.photonRequestId) {
                    this.$wire.call('setPhotonSuggestions', [], 'Адресу не знайдено');
                }
            }
        },
        getPhotonBiasCoordinates() {
            const fallback = { lat: 48.45, lon: 34.98 };
            const mapCenter = window.POOF?.map?.instance?.getCenter?.();

            if (mapCenter && Number.isFinite(mapCenter.lat) && Number.isFinite(mapCenter.lng)) {
                return {
                    lat: mapCenter.lat,
                    lon: mapCenter.lng,
                };
            }

            if (Number.isFinite(this.lat) && Number.isFinite(this.lng)) {
                return {
                    lat: this.lat,
                    lon: this.lng,
                };
            }

            return fallback;
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

                @if (!empty($suggestions))
                    <div class="absolute z-50 mt-1 w-full overflow-hidden rounded-xl bg-neutral-900 border border-neutral-700 shadow-xl">
                        @foreach ($suggestions as $item)
                            <button
                                type="button"
                                wire:click="selectSuggestion({{ $loop->index }})"
                                class="block w-full text-left px-4 py-2 text-sm transition {{ $activeSuggestionIndex === $loop->index ? 'bg-neutral-800' : 'hover:bg-neutral-800' }}"
                            >
                                <div class="font-medium text-gray-100">{{ $item['line1'] ?? $item['label'] }}</div>
                                @if(!empty($item['line2']))
                                    <div class="text-xs text-gray-400">{{ $item['line2'] }}</div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @elseif (!empty($suggestionsMessage))
                    <div class="absolute z-50 mt-1 w-full rounded-xl bg-neutral-900 border border-neutral-700 shadow-xl px-4 py-3 text-sm text-gray-300">
                        {{ $suggestionsMessage }}
                    </div>
                @endif
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
