<?php

namespace App\Livewire\Client;

use App\Models\Order;
use App\Models\ClientAddress;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
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

    /** UI подпись адреса */
    public string $address_text = '';

    // 🆕 дом — отдельно
    public ?string $street = null;
    public ?string $house  = null;
    public ?string $city   = null;

    /** Координаты — ИСТИНА для курьера */
    public ?float $lat = null;
    public ?float $lng = null;
	
	/** 🔑 координаты пришли из адресной книги */
    public bool $coordsFromAddressBook = false;

    /** детали адреса */
    public ?string $entrance  = null;
    public ?string $floor     = null;
    public ?string $apartment = null;
    public ?string $intercom  = null;

    /** комментарий к заказу */
    public ?string $comment = null;

    /**
     * 🔒 Guard: когда мы программно меняем street/house/city/address_text,
     * не нужно, чтобы updated* хуки запускали геокодинг/перезапись и ломали синхронизацию.
     */
    

    /* =========================================================
     |  SCHEDULE
     | ========================================================= */

    public ?string $scheduled_date = null;
    public ?string $scheduled_time_from = null;
    public ?string $scheduled_time_to = null;

    /** Slot slider index (0..6) */
    public int $timeSlot = 0;

    /** 7 slots, last is reserved (disabled) */
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
	
	public bool $suppressAddressHooks = false;

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
            // а обязательность проверим отдельной "взрослой" проверкой
            'lat'                 => ['nullable', 'numeric', 'between:-90,90'],
            'lng'                 => ['nullable', 'numeric', 'between:-180,180'],

            'promo_code'          => ['nullable', 'string', 'max:50'],

            'is_trial'            => ['boolean'],
            'trial_days'          => ['nullable', 'integer', 'in:1,3'],
        ];
    }

    /* =========================================================
     |  LIFECYCLE
     | ========================================================= */

    public function mount(): void
    {
        if (! $this->scheduled_date) {
            $this->scheduled_date = Carbon::today()->toDateString();
        }

        $this->reloadAddresses();
        $this->updateIsCustomDate();
        $this->trial_used = $this->userAlreadyUsedTrial();

        $idx = $this->firstAvailableSlotIndex();
        $this->applyTimeSlot($idx);

        $this->recalculatePrice();

        // ✅ карта инициализируется один раз на клиенте
        $this->dispatch('map:init');
    }

    /* =========================================================
     |  ADDRESS ACTIONS (TOP-APP FLOW)
     | ========================================================= */

    public function reloadAddresses(): void
    {
        $this->addresses = ClientAddress::where('user_id', auth()->id())
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
		$address = ClientAddress::where('id', $addressId)
			->where('user_id', auth()->id())
			->firstOrFail();

		// 🔒 ВАЖНО: заморозить хуки на время программного заполнения
		$this->suppressAddressHooks = true;

		$this->address_id = $address->id;

		$this->street = $address->street;
		$this->house  = $address->house;
		$this->city   = $address->city;

		$this->address_text = trim(
			collect([$address->street, $address->house])->filter()->implode(' ')
		);

		$this->entrance  = $address->entrance;
		$this->floor     = $address->floor;
		$this->apartment = $address->apartment;
		$this->intercom  = $address->intercom;

		// ✅ точные координаты из БД
		$this->lat = $address->lat;
		$this->lng = $address->lng;

		// ✅ источник — адресная книга
		$this->coordsFromAddressBook = true;

		// 🔓 разморозить хуки
		$this->suppressAddressHooks = false;

		// ✅ двигаем маркер по точным координатам
		$this->pushMarkerToMap();

		$this->dispatch('sheet:close', name: 'addressPicker');
	}

    protected function syncAddressText(): void
    {
        // можно оставить только "улица дом", как у тебя
        $this->address_text = trim(
            collect([$this->street, $this->house])
                ->filter()
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

        // НЕ трогаем lat/lng здесь (координаты — истина)
        $this->syncStreetFromAddressText();
    }

	public function updatedStreet(): void
	{
		if ($this->suppressAddressHooks) {
			return;
		}

		$this->coordsFromAddressBook = false;
		$this->address_id = null;

		$this->syncAddressText();
	}

	public function updatedHouse(): void
	{
		// 1️⃣ программное изменение (selectAddress / reverseGeocode)
		if ($this->suppressAddressHooks) {
			return;
		}

		// 2️⃣ пользователь начал править адрес вручную
		$this->coordsFromAddressBook = false;
		$this->address_id = null;

		// 3️⃣ теперь geocode РАЗРЕШЁН
		$this->geocodeFromFields();
	}

    /* =========================================================
     |  MAP → LIVEWIRE
     | ========================================================= */

	#[On('set-location')]
	public function setLocation(float $lat, float $lng): void
	{
		$this->lat = $lat;
		$this->lng = $lng;

		// пользователь двигает точку сам
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

		// Livewire v3 → Browser Event
		$this->dispatch(
			'map:set-marker',
			lat: (float) $this->lat,
			lng: (float) $this->lng,
		);
	}
    /**
     * Точка -> адрес (reverse geocode)
     */
    protected function reverseGeocodeFromPoint(float $lat, float $lng): void
    {
        try {
            $response = Http::get(
                'https://maps.googleapis.com/maps/api/geocode/json',
                [
                    'latlng'   => "{$lat},{$lng}",
                    'key'      => config('geocoding.google.key'),
                    'language' => 'uk',
                ]
            );

            if (! $response->ok()) {
                return;
            }

            $components = data_get($response->json(), 'results.0.address_components');
            if (! is_array($components)) {
                return;
            }

            $street = collect($components)->first(fn ($c) => in_array('route', $c['types'], true));
            $house  = collect($components)->first(fn ($c) => in_array('street_number', $c['types'], true));
            $city   = collect($components)->first(fn ($c) => in_array('locality', $c['types'], true));

			$this->suppressAddressHooks = true;

			try {
				$this->street = $street['long_name'] ?? $this->street;
				$this->house  = $house['long_name'] ?? $this->house;
				$this->city   = $city['long_name'] ?? $this->city;

				$this->syncAddressText();
			} finally {
				$this->suppressAddressHooks = false;
			}

        } catch (\Throwable $e) {
            // silent fail
        }
    }

    /**
     * Адрес -> точка (geocode)
     */
    protected function geocodeFromFields(): void
    {
        if (! $this->street || ! $this->house) {
            return;
        }

        $city = $this->city ?: 'Kyiv';
        $query = trim("{$city}, {$this->street} {$this->house}");

        try {
            $response = Http::get(
                'https://maps.googleapis.com/maps/api/geocode/json',
                [
                    'address'  => $query,
                    'key'      => config('geocoding.google.key'),
                    'language' => 'uk',
                ]
            );

            if (! $response->ok()) {
                return;
            }

            $location = data_get($response->json(), 'results.0.geometry.location');
            if (! is_array($location) || ! isset($location['lat'], $location['lng'])) {
                return;
            }

            $this->lat = (float) $location['lat'];
            $this->lng = (float) $location['lng'];

            $this->pushMarkerToMap();

        } catch (\Throwable $e) {
            // silent fail
        }
    }

    /* =========================================================
     |  ADDRESS HELPERS
     | ========================================================= */

    protected function syncStreetFromAddressText(): void
    {
        if (! $this->address_text) {
            return;
        }

        $parts = array_map('trim', explode(',', $this->address_text));

        // MVP: первый сегмент — улица
        $this->street = $parts[0] ?? $this->street;

        // город — если нужно
        if (! $this->city && isset($parts[1])) {
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

            if ($isToday) {
                $from = Carbon::createFromFormat('H:i', $slot['from'])->setDate(
                    $now->year,
                    $now->month,
                    $now->day
                );

                if ($from->greaterThan($now)) {
                    return $idx;
                }
            } else {
                return $idx;
            }
        }

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

        $this->isCustomDate = ! in_array(
            $this->scheduled_date,
            [$today, $tomorrow],
            true
        );
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

        if (!($this->timeSlots[$idx]['enabled'] ?? true)) {
            for ($j = $idx - 1; $j >= 0; $j--) {
                if (($this->timeSlots[$j]['enabled'] ?? true) === true) {
                    $idx = $j;
                    break;
                }
            }
        }

        $this->timeSlot = $idx;
        $this->scheduled_time_from = $this->timeSlots[$idx]['from'] ?? null;
        $this->scheduled_time_to   = $this->timeSlots[$idx]['to'] ?? null;
    }

    public function updatedScheduledDate(): void
    {
        $this->updateIsCustomDate();

        $idx = $this->firstAvailableSlotIndex();
        $this->applyTimeSlot($idx);

        $this->recalculatePrice();

        // ⚠️ не трогаем map:init тут, чтобы не сбивать маркер/карту
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

    public function getIsCustomDateProperty(): bool
    {
        if (! $this->scheduled_date) {
            return false;
        }

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        return ! in_array($this->scheduled_date, [$today, $tomorrow], true);
    }

    #[On('set-scheduled-date')]
    public function setScheduledDate(string $date): void
    {
        $this->scheduled_date = $date;
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
        $this->bags_count = max(1, min(3, $count));

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
        $this->trial_days = in_array($days, [1, 3], true) ? $days : 1;

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
            : Order::calcPriceByBags($this->bags_count);
    }

    /* =========================================================
     |  COORDS GUARD (CRITICAL)
     | ========================================================= */

    protected function validateCoordinatesOrFail(): void
    {
        // ✅ корректная проверка на null (не через truthy)
        if (is_null($this->lat) || is_null($this->lng)) {
            $this->addError('address_text', 'Оберіть збережену адресу або вкажіть точку на мапі.');
            return;
        }

        // Если выбран address_id, но там пустые coords — значит адрес ещё не “подтверждён”
        if ($this->address_id && (is_null($this->lat) || is_null($this->lng))) {
            $this->addError('address_text', 'Ця адреса потребує уточнення. Відкрийте її в адресній книзі та збережіть з точкою.');
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





