<?php

namespace App\Livewire\Client;

use App\Models\ClientAddress;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class OrderCreate extends Component
{
    /* =========================================================
     |  STATE / PROPERTIES
     | ========================================================= */

    /** Адреса пользователя (для picker) */
    public Collection $addresses;

    /* =========================================================
     |  ADDRESS
     | ========================================================= */

    /** ✅ выбранный сохранённый адрес (если null — ввод вручную) */
    public ?int $address_id = null;

    /** UI подпись адреса (как показываем пользователю) */
    public string $address_text = '';

    /** Разбивка адреса по полям */
    public ?string $street = null;
    public ?string $house  = null;
    public ?string $city   = null;

    /** Координаты — ИСТИНА для курьера */
    public ?float $lat = null;
    public ?float $lng = null;

    /** 🔑 координаты пришли из адресной книги */
    public bool $coordsFromAddressBook = false;
	
	/**
	 * Точность адреса:
	 * none   — нет координат
	 * approx — координаты из geocode
	 * exact  — координаты подтверждены (address book / ручная точка)
	 */
	public string $address_precision = 'none';

	/** debounce token для отложенного geocode */
	protected ?string $geocodeToken = null;

    /** детали адреса */
    public ?string $entrance  = null;
    public ?string $floor     = null;
    public ?string $apartment = null;
    public ?string $intercom  = null;

    /** комментарий к заказу */
    public ?string $comment = null;

    /**
     * 🔒 Guard: когда мы программно меняем street/house/city/address_text,
     * updated* хуки НЕ должны запускать геокодинг / сбивать синхронизацию.
     */
    public bool $suppressAddressHooks = false;

    /* =========================================================
     |  SCHEDULE
     | ========================================================= */

    public ?string $scheduled_date = null;
    public ?string $scheduled_time_from = null;
    public ?string $scheduled_time_to = null;

    /** Slot index (0..n-1) */
    public int $timeSlot = 0;

    /** slots */
    public array $timeSlots = [
        ['from' => '08:00', 'to' => '10:00', 'enabled' => true],
        ['from' => '10:00', 'to' => '12:00', 'enabled' => true],
        ['from' => '12:00', 'to' => '14:00', 'enabled' => true],
        ['from' => '14:00', 'to' => '16:00', 'enabled' => true],
        ['from' => '16:00', 'to' => '18:00', 'enabled' => true],
        ['from' => '18:00', 'to' => '20:00', 'enabled' => true],
        ['from' => '20:00', 'to' => '22:00', 'enabled' => false], // резерв (поки не можна)
    ];

    /** ✅ чтобы не создавать динамическое свойство (PHP 8.2) */
    public bool $isCustomDate = false;

    /* =========================================================
     |  OPTIONS
     | ========================================================= */

    public string $handover_type = Order::HANDOVER_DOOR;
    public int $bags_count = 1;

    /* =========================================================
     |  PROMO / TRIAL
     | ========================================================= */

    public ?string $promo_code = null;

    public bool $is_trial = false;
    public int $trial_days = 1;

    /** нужен для UI (disabled на trial options) */
    public bool $trial_used = false;

    /* =========================================================
     |  PRICE
     | ========================================================= */

    public int $price = 0;

    /* =========================================================
     |  POPUP STATE
     | ========================================================= */

    public bool $showPaymentModal = false;
    public bool $showTrialBlockedModal = false;
    public ?int $createdOrderId = null;

    /* =========================================================
     |  VALIDATION
     | ========================================================= */

    protected function rules(): array
    {
        return [
            'address_text'        => ['required', 'string', 'min:3'],
            'scheduled_date'      => ['required', 'date'],
            'scheduled_time_from' => ['required', 'string'],
            'scheduled_time_to'   => ['nullable', 'string'],

            'handover_type'       => ['required', 'in:' . Order::HANDOVER_DOOR . ',' . Order::HANDOVER_HAND],
            'bags_count'          => ['required', 'integer', 'min:1', 'max:3'],

            // координаты валидируем как числа,
            // а обязательность проверим отдельной проверкой validateCoordinatesOrFail()
            'lat'                 => ['nullable', 'numeric', 'between:-90,90'],
            'lng'                 => ['nullable', 'numeric', 'between:-180,180'],

            'promo_code'          => ['nullable', 'string', 'max:50'],

            'is_trial'            => ['boolean'],
            'trial_days'          => ['nullable', 'integer', 'in:1,3'],
        ];
    }

    protected function messages(): array
    {
        return [
            'address_text.required' => 'Вкажіть адресу.',
            'address_text.min'      => 'Адреса занадто коротка.',
            'scheduled_date.required' => 'Оберіть дату.',
            'scheduled_date.date'     => 'Некоректна дата.',
            'scheduled_time_from.required' => 'Оберіть час.',
            'bags_count.min' => 'Мінімум 1 пакет.',
            'bags_count.max' => 'Максимум 3 пакети.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'address_text' => 'адреса',
            'scheduled_date' => 'дата',
            'scheduled_time_from' => 'час',
            'bags_count' => 'кількість пакетів',
        ];
    }

    /* =========================================================
     |  LIFECYCLE
     | ========================================================= */

	public function mount(): void
	{
		// =========================================
		// 🔁 Повтор заказа (СТАРЫЕ и НОВЫЕ)
		// =========================================
		$repeatId = request()->integer('repeat');

		if ($repeatId) {
			$order = \App\Models\Order::query()
				->where('id', $repeatId)
				->where('client_id', auth()->id())
				->first();

			if ($order) {
				$this->hydrateFromOrder($order);
			}
		}

		// =========================================
		// ✅ Адрес из адресной книги (если НЕ repeat)
		// =========================================
		if (! $repeatId) {
			$this->address_id = request()->integer('address_id');

			if ($this->address_id) {
				$this->loadAddressFromBook($this->address_id);
			}
		}

		// =========================================
		// ⏱ Дата и время
		// =========================================
		if (! $this->scheduled_date) {
			$this->scheduled_date = Carbon::today()->toDateString();
		}

		$this->reloadAddresses();
		$this->trial_used = $this->userAlreadyUsedTrial();

		$this->updateIsCustomDate();

		// ✅ выбираем ближайший доступный слот
		$this->applyTimeSlot($this->firstAvailableSlotIndex());

		// 💰 расчёт цены (уже с адресом, если он был)
		$this->recalculatePrice();

		// 🗺 карта инициализируется один раз на клиенте
		$this->dispatch('map:init');
	}

	
			
	protected function loadAddressFromBook(int $addressId): void
	{
		$address = \App\Models\ClientAddress::where('id', $addressId)
			->where('user_id', auth()->id())
			->first();

		if (! $address) {
			return;
		}

		$this->suppressAddressHooks = true;

		$this->coordsFromAddressBook = true;
		$this->address_precision = 'exact';

		// UI
		$this->address_text = $address->address_text ?? $address->full_address;

		// структура
		$this->street = $address->street;
		$this->house  = $address->house;
		$this->city   = $address->city;

		// координаты — истина
		$this->lat = $address->lat;
		$this->lng = $address->lng;

		// детали
		$this->entrance  = $address->entrance;
		$this->floor     = $address->floor;
		$this->apartment = $address->apartment;
		$this->intercom  = $address->intercom;

		$this->suppressAddressHooks = false;

		// 🔔 карта
		if ($this->lat && $this->lng) {
			$this->dispatch('map:set-marker', lat: $this->lat, lng: $this->lng);
			$this->dispatch('map:set-marker-precision', precision: 'exact');
		}
	}

	protected function hydrateFromOrder(\App\Models\Order $order): void
	{
		$this->suppressAddressHooks = true;

		try {
			// ✅ Новый заказ (есть address_id)
			if ($order->address_id) {
				$this->loadAddressFromBook($order->address_id);
				return;
			}

			// 🧓 Старый заказ (address_id = null)
			$this->address_id   = null;
			$this->address_text = $order->address_text ?? '';

			$this->street = null;
			$this->house  = null;
			$this->city   = null;

			$this->entrance  = $order->entrance;
			$this->floor     = $order->floor;
			$this->apartment = $order->apartment;
			$this->intercom  = $order->intercom;

			$this->lat = $order->lat;
			$this->lng = $order->lng;

			$this->coordsFromAddressBook = true; // 🔑 доверяем координатам из истории
			$this->address_precision = ($this->lat && $this->lng)
				? 'exact'
				: 'none';

		} finally {
			$this->suppressAddressHooks = false;
		}

		// 🔁 ВОССТАНОВЛЕНИЕ СТРУКТУРЫ АДРЕСА ИЗ КООРДИНАТ
		if (! $order->address_id && $this->lat && $this->lng) {
			$this->hydrateAddressFromCoords($this->lat, $this->lng);
		}

		// 🔔 карта
		if ($this->lat && $this->lng) {
			$this->dispatch('map:set-marker', lat: $this->lat, lng: $this->lng);
			$this->dispatch('map:set-marker-precision', precision: 'approx');
		}
	}
	
	
	protected function hydrateAddressFromCoords(float $lat, float $lng): void
	{
		try {
			$geocoder = app(\App\Services\Geocoding\Geocoder::class);
			$point = $geocoder->reverse($lat, $lng);

			if (! $point) return;

			$this->suppressAddressHooks = true;

			// 1️⃣ UI строка
			if (! empty($point->address)) {
				$this->address_text = $point->address;
			}

			// 2️⃣ components (если есть)
			foreach ($point->components ?? [] as $c) {
				$types = $c['types'] ?? [];
				$name  = $c['long_name'] ?? $c['name'] ?? null;
				if (! $name) continue;

				if (in_array('route', $types, true)) {
					$this->street ??= $name;
				}

				if (
					in_array('street_number', $types, true) ||
					in_array('house_number', $types, true)
				) {
					$this->house ??= $name;
				}

				if (
					in_array('locality', $types, true) ||
					in_array('city', $types, true)
				) {
					$this->city ??= $name;
				}
			}

			// 🔥 3️⃣ FALLBACK: парсим из address_text
			if ((! $this->street || ! $this->house) && $this->address_text) {

				// примеры:
				// "Khreshchatyk Street 13"
				// "вулиця Хрещатик, 13"
				// "Хрещатик 13, Київ"

				if (preg_match(
					'/^(.*?)[,\s]+(\d+[A-Za-zА-Яа-яІЇЄієї\-\/]*)/u',
					$this->address_text,
					$m
				)) {
					$this->street ??= trim($m[1]);
					$this->house  ??= trim($m[2]);
				}
			}

			$this->address_precision = 'approx';

		} catch (\Throwable $e) {
			// тихо
		} finally {
			$this->suppressAddressHooks = false;
		}
	}

    /* =========================================================
     |  ADDRESS ACTIONS (TOP-APP FLOW)
     | ========================================================= */

    public function reloadAddresses(): void
    {
        $this->addresses = ClientAddress::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('is_default')
            ->latest('id')
            ->get();
    }

    #[On('address-saved')]
    public function onAddressSaved(): void
    {
        $this->reloadAddresses();
    }

	public function selectAddress(int $addressId): void
	{
		$address = ClientAddress::query()
			->where('id', $addressId)
			->where('user_id', auth()->id())
			->firstOrFail();

		$this->suppressAddressHooks = true;

		try {
			$this->address_id = $address->id;

			$this->street = $address->street;
			$this->house  = $address->house;
			$this->city   = $address->city;

			$this->syncAddressText();

			$this->entrance  = $address->entrance;
			$this->floor     = $address->floor;
			$this->apartment = $address->apartment;
			$this->intercom  = $address->intercom;

			// координаты из БД
			$this->lat = $address->lat;
			$this->lng = $address->lng;

			// источник — адресная книга
			$this->coordsFromAddressBook = true;

			// 🔑 точность адреса
			$this->address_precision = ($this->lat !== null && $this->lng !== null)
				? 'exact'
				: 'none';

			// 🔒 сбрасываем возможный отложенный geocode
			$this->geocodeToken = null;

		} finally {
			$this->suppressAddressHooks = false;
		}

		// двигаем маркер ТОЛЬКО если координаты есть
		if ($this->address_precision === 'exact') {
			$this->pushMarkerToMap();
		}

		$this->dispatch('sheet:close', name: 'addressPicker');
	}

    protected function syncAddressText(): void
    {
        // отображаем только "улица дом" — быстро и читаемо
        $this->address_text = trim(
            collect([$this->street, $this->house])
                ->filter(fn ($v) => filled($v))
                ->implode(' ')
        );
    }

    /* =========================================================
     |  ADDRESS FIELD HOOKS
     | ========================================================= */

    public function updatedAddressText(): void
    {
        if ($this->suppressAddressHooks) {
            return;
        }

        // пользователь начал править вручную — это уже не адрес из книги
		$this->address_id = null;
		$this->coordsFromAddressBook = false;
		$this->address_precision = 'none';

        // НЕ трогаем lat/lng тут — координаты “истина”
        $this->syncStreetFromAddressText();
    }

    public function updatedStreet(): void
    {
        if ($this->suppressAddressHooks) {
            return;
        }

        $this->coordsFromAddressBook = false;
        $this->address_id = null;
		$this->address_precision = 'none';
		

        $this->syncAddressText();
        // геокодинг тут не запускаем — пусть дом/номер станет известен
    }

    public function updatedHouse(): void
    {
        // 1) программное изменение (selectAddress / reverseGeocode)
        if ($this->suppressAddressHooks) {
            return;
        }

        // 2) пользователь начал править адрес вручную
		$this->coordsFromAddressBook = false;
		$this->address_id = null;
		$this->address_precision = 'none';

        // 3) теперь geocode разрешён
        $this->syncAddressText();
        $this->scheduleGeocode();
    }

    /* =========================================================
     |  MAP → LIVEWIRE
     | ========================================================= */

	#[On('set-location')]
	public function setLocation(float $lat, float $lng): void
	{
		$this->lat = $lat;
		$this->lng = $lng;

		$this->address_precision = 'exact';
		$this->coordsFromAddressBook = false;
		$this->address_id = null;

		$this->reverseGeocodeFromPoint($lat, $lng);
	}

    /**
     * 🔑 Пуш маркера на карту (Livewire v3 event)
     */
    protected function pushMarkerToMap(): void
    {
        if ($this->lat === null || $this->lng === null) {
            return;
        }

        $this->dispatch('map:set-marker', lat: (float) $this->lat, lng: (float) $this->lng);
    }

    /* =========================================================
     |  GEOCODING (GOOGLE)
     | ========================================================= */

	/**
	 * Универсальный Google Geocode с кэшем
	 */
	protected function googleGeocodeCached(array $params, string $cacheKey): ?array
	{
		$cached = Cache::get($cacheKey);
		if (is_array($cached)) {
			return $cached;
		}

		try {
			$response = Http::timeout(4)
				->retry(1, 200)
				->get('https://maps.googleapis.com/maps/api/geocode/json', $params + [
					'key'      => config('geocoding.google.key'),
					'language' => 'uk',
				]);

			if (! $response->ok()) {
				return null;
			}

			$json = $response->json();

			// ✅ кэшируем только валидные ответы
			if (data_get($json, 'status') === 'OK' && is_array(data_get($json, 'results'))) {
				Cache::put($cacheKey, $json, now()->addHours(24));
			}

			return $json;
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * Планируем отложенный geocode (debounce)
	 */
	protected function scheduleGeocode(): void
	{
		$token = uniqid('geo_', true);
		$this->geocodeToken = $token;

		// ✅ это browser event, JS сделает задержку и потом вызовет geocode:debounced
		$this->dispatch('geocode:schedule', token: $token);
	}

	/**
	 * 🔑 Livewire v3 listener
	 * Выполняется ТОЛЬКО если токен актуален
	 */
	#[On('geocode:debounced')]
	public function runDebouncedGeocode(string $token): void
	{
		if ($this->geocodeToken !== $token) {
			return;
		}

		$this->geocodeFromFields();
	}

	/**
	 * Точка → адрес (reverse geocode)
	 * Используется при ручном перемещении маркера
	 */
	protected function reverseGeocodeFromPoint(float $lat, float $lng): void
	{
		$cacheKey = 'reverse_geocode:' . md5($lat . ',' . $lng);

		$json = $this->googleGeocodeCached(
			['latlng' => "{$lat},{$lng}"],
			$cacheKey
		);

		$streetName = null;
		$houseNumber = null;
		$cityName = null;

		$components = data_get($json, 'results.0.address_components');
		if (is_array($components)) {
			$street = collect($components)->first(
				fn ($c) => in_array('route', $c['types'] ?? [], true)
			);
			$house = collect($components)->first(
				fn ($c) => in_array('street_number', $c['types'] ?? [], true)
			);
			$city = collect($components)->first(
				fn ($c) => in_array('locality', $c['types'] ?? [], true)
			);

			$streetName = $street['long_name'] ?? null;
			$houseNumber = $house['long_name'] ?? null;
			$cityName = $city['long_name'] ?? null;
		}

		if (! $streetName || ! $cityName) {
			try {
				$fallback = Http::timeout(5)
					->acceptJson()
					->get(url('/api/geocode'), ['lat' => $lat, 'lng' => $lng])
					->json('0');

				if (is_array($fallback)) {
					$streetName ??= trim((string) ($fallback['street'] ?? '')) ?: null;
					$houseNumber ??= trim((string) ($fallback['house'] ?? '')) ?: null;
					$cityName ??= trim((string) ($fallback['city'] ?? '')) ?: null;
				}
			} catch (\Throwable) {
				// silently ignore, we still keep known values
			}
		}

		$this->suppressAddressHooks = true;

		try {
			$this->street = $streetName ?? $this->street;
			$this->house  = $houseNumber ?? $this->house;
			$this->city   = $cityName ?? $this->city;

			$this->syncAddressText();
		} finally {
			$this->suppressAddressHooks = false;
		}
	}

	/**
	 * Адрес → точка (geocode)
	 * ⚠️ Используется ТОЛЬКО для приблизительного определения
	 */
	protected function geocodeFromFields(): void
	{
		if (! filled($this->street) || ! filled($this->house)) {
			return;
		}

		// ❗ точные координаты никогда не перезаписываем
		if ($this->address_precision === 'exact') {
			return;
		}

		$city = filled($this->city) ? $this->city : 'Kyiv';

		$cacheKeySource = "{$city}|{$this->street}|{$this->house}";
		$addressQuery   = str_replace('|', ', ', $cacheKeySource);

		$json = $this->googleGeocodeCached(
			['address' => $addressQuery],
			'geocode:' . md5($cacheKeySource)
		);

		$location = data_get($json, 'results.0.geometry.location');
		if (! is_array($location) || ! isset($location['lat'], $location['lng'])) {
			return;
		}

		$this->lat = (float) $location['lat'];
		$this->lng = (float) $location['lng'];

		// ⚠️ это приблизительная точка
		$this->address_precision = 'approx';

		$this->pushMarkerToMap();
	}

    /* =========================================================
     |  ADDRESS HELPERS
     | ========================================================= */

    protected function syncStreetFromAddressText(): void
    {
        if (! filled($this->address_text)) {
            return;
        }

        $parts = array_map('trim', explode(',', $this->address_text));

        // MVP: первый сегмент — улица/основная часть
        $this->street = $parts[0] ?? $this->street;

        // город — если нужно
        if (! filled($this->city) && isset($parts[1])) {
            $this->city = $parts[1];
        }
    }

    /* =========================================================
     |  TIME SLOTS (HELPERS)
     | ========================================================= */

    protected function firstAvailableSlotIndex(): int
    {
        $now = now();

        $selectedDate = $this->scheduled_date
            ? Carbon::parse($this->scheduled_date)
            : Carbon::today();

        $isToday = $selectedDate->isSameDay($now);

        foreach ($this->timeSlots as $idx => $slot) {
            if (!($slot['enabled'] ?? true)) {
                continue;
            }

            if (! $isToday) {
                return (int) $idx;
            }

            $from = Carbon::createFromFormat('H:i', (string) $slot['from'])->setDate(
                $now->year,
                $now->month,
                $now->day
            );

            if ($from->greaterThan($now)) {
                return (int) $idx;
            }
        }

        // если все “сегодняшние” прошли — оставим первый (UI сам может подсветить)
        return 0;
    }

    protected function updateIsCustomDate(): void
    {
        if (! $this->scheduled_date) {
            $this->isCustomDate = false;
            return;
        }

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $this->isCustomDate = ! in_array($this->scheduled_date, [$today, $tomorrow], true);
    }

    public function applyTimeSlot(int $idx): void
    {
        $count = count($this->timeSlots);

        if ($count === 0) {
            $this->timeSlot = 0;
            $this->scheduled_time_from = null;
            $this->scheduled_time_to = null;
            return;
        }

        $idx = max(0, min($idx, $count - 1));

        // если слот выключен — ищем ближайший включенный вперёд, потом назад
        if (!($this->timeSlots[$idx]['enabled'] ?? true)) {
            $found = null;

            for ($j = $idx; $j < $count; $j++) {
                if (($this->timeSlots[$j]['enabled'] ?? true) === true) {
                    $found = $j;
                    break;
                }
            }

            if ($found === null) {
                for ($j = $idx; $j >= 0; $j--) {
                    if (($this->timeSlots[$j]['enabled'] ?? true) === true) {
                        $found = $j;
                        break;
                    }
                }
            }

            $idx = $found ?? 0;
        }

        $this->timeSlot = (int) $idx;
        $this->scheduled_time_from = $this->timeSlots[$idx]['from'] ?? null;
        $this->scheduled_time_to   = $this->timeSlots[$idx]['to'] ?? null;
    }

    public function updatedScheduledDate(): void
    {
        $this->updateIsCustomDate();

        $this->applyTimeSlot($this->firstAvailableSlotIndex());

        $this->recalculatePrice();

        // ⚠️ map:init тут не трогаем, чтобы не сбивать карту
    }

    public function selectTimeSlot(string $from, string $to): void
    {
        $this->scheduled_time_from = $from;
        $this->scheduled_time_to = $to;

        foreach ($this->timeSlots as $idx => $slot) {
            if (($slot['from'] ?? null) === $from && ($slot['to'] ?? null) === $to) {
                if (($slot['enabled'] ?? true) === true) {
                    $this->timeSlot = (int) $idx;
                }
                break;
            }
        }
    }

    // ✅ computed accessor — просто отдаёт единую “правду”
    public function getIsCustomDateProperty(): bool
    {
        return $this->isCustomDate;
    }

    #[On('set-scheduled-date')]
    public function setScheduledDate(string $date): void
    {
        $this->scheduled_date = $date;
        $this->updatedScheduledDate();
    }

    #[On('set-time-slot')]
    public function setTimeSlot(int $index): void
    {
        $this->applyTimeSlot($index);
    }

    /* =========================================================
     |  UI ACTIONS
     | ========================================================= */

    public function selectBags(int $count): void
    {
        $this->bags_count = max(1, min(3, (int) $count));

        // Trial не совместим с количеством пакетов > 1 (по твоей логике)
        if ($this->is_trial) {
            $this->disableTrial();
            return;
        }

        $this->recalculatePrice();
    }

    public function selectTrial(int $days): void
    {
        if ($this->trial_used) {
            $this->showTrialBlockedModal = true;
            return;
        }

        $this->is_trial = true;
        $this->trial_days = in_array($days, [1, 3], true) ? (int) $days : 1;

        // trial всегда = 1 пакет
        $this->bags_count = 1;
        $this->price = 0;
    }

    protected function userAlreadyUsedTrial(): bool
    {
        return Order::query()
            ->where('client_id', Auth::id())
            ->where('is_trial', true)
            ->exists();
    }

    public function disableTrial(): void
    {
        $this->is_trial = false;
        $this->trial_days = 1;
        $this->recalculatePrice();
    }

    /* =========================================================
     |  PRICE
     | ========================================================= */

    protected function recalculatePrice(): void
    {
        $this->price = $this->is_trial
            ? 0
            : (int) Order::calcPriceByBags($this->bags_count);
    }

    /* =========================================================
     |  COORDS GUARD (CRITICAL)
     | ========================================================= */

	protected function validateCoordinatesOrFail(): void
	{
		if (is_null($this->lat) || is_null($this->lng)) {
			$this->addError(
				'address_text',
				'Вкажіть адресу або точку на мапі.'
			);
			return;
		}

		// 🔒 Требуем уточнение ТОЛЬКО если:
		// - координаты не из адресной книги
		// - и адрес не подтверждён
		if (
			$this->address_precision === 'approx'
			&& ! $this->coordsFromAddressBook
		) {
			$this->addError(
				'address_text',
				'Будь ласка, уточніть точку на мапі.'
			);
			return;
		}

		$this->resetErrorBag('address_text');
	}

    /* =========================================================
     |  SUBMIT (CREATE ORDER + POPUP)
     | ========================================================= */

    public function submit(): void
    {
        if ($this->is_trial && $this->trial_used) {
            $this->showTrialBlockedModal = true;
            return;
        }

        $this->validate();
        $this->validateCoordinatesOrFail();

        if ($this->getErrorBag()->has('address_text')) {
            return;
        }

        $this->recalculatePrice();

        $order = Order::create([
            'client_id'           => Auth::id(),
            'order_type'          => Order::TYPE_ONE_TIME,
            'status'              => Order::STATUS_NEW,
            'payment_status'      => $this->is_trial ? Order::PAY_PAID : Order::PAY_PENDING,

            // 🔗 связь с адресной книгой (если выбран)
            'address_id'          => $this->address_id,
			
			'address_text'        => $this->address_text,
            'lat'                 => $this->lat,
            'lng'                 => $this->lng,

            'entrance'            => $this->entrance,
            'floor'               => $this->floor,
            'apartment'           => $this->apartment,
            'intercom'            => $this->intercom,
            'comment'             => $this->comment,

            'scheduled_date'      => $this->scheduled_date,
            'scheduled_time_from' => $this->scheduled_time_from,
            'scheduled_time_to'   => $this->scheduled_time_to,

            'handover_type'       => $this->handover_type,
            'bags_count'          => $this->bags_count,
            'price'               => $this->price,

            'promo_code'          => $this->promo_code,
            'is_trial'            => $this->is_trial,
            'trial_days'          => $this->is_trial ? $this->trial_days : null,
        ]);

        $this->createdOrderId = $order->id;
        $this->showPaymentModal = true;

        if ($this->is_trial) {
            $this->trial_used = true;
        }
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
    }

    /* =========================================================
     |  VIEW
     | ========================================================= */

    public function render()
    {
        return view('livewire.client.order-create', [
            'timeSlots' => $this->timeSlots,
            'pricing'   => Order::bagsPricing(),
            'addresses' => $this->addresses,
        ])->layout('layouts.client');
    }
}
