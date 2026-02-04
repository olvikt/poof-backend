<?php

namespace App\Livewire\Client;

use Livewire\Component;
use App\Models\ClientAddress;
use App\Services\Geocoding\Geocoder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AddressForm extends Component
{
    /* =========================================================
     | STATE
     |=========================================================*/

    public ?int $addressId = null;

    /** apartment | house */
    public string $building_type = 'apartment';

    public string $label = 'home';
    public ?string $title = null;

    // UI
    public ?string $search = null;
    public array $suggestions = [];

    // Координаты — ИСТИНА
    public ?float $lat = null;
    public ?float $lng = null;

    // Google meta
    public ?string $place_id = null;

    // Детали (для apartment)
    public ?string $entrance = null;
    public ?string $intercom = null;
    public ?string $floor = null;
    public ?string $apartment = null;

    // Адрес
    public ?string $city = null;
    public ?string $street = null;
    public ?string $house = null;
	
	 // -----------------------------
    // INTERNAL FLAGS (НЕ UI)
    // -----------------------------
    protected bool $houseTouchedManually = false;
    protected bool $updatingHouseFromMap = false;
	

    /* =========================================================
     | EVENTS
     |=========================================================*/

    protected $listeners = [
        'address:open'       => 'open',
        'address:set-coords' => 'setCoords',
    ];

    /* =========================================================
     | OPEN / LOAD
     |=========================================================*/

public function open(?int $addressId = null): void
{
    $this->addressId = $addressId;

    if ($addressId) {
        $this->loadAddress($addressId);
    } else {
        $this->resetForm();
    }

    // открываем sheet
    $this->dispatch('sheet:open', name: 'editAddress');

    // 🔒 СТРАХОВКА:
    // если координаты уже есть — повторно синхронизируем маркер
    // (карта к этому моменту уже смонтируется)
    if ($this->lat !== null && $this->lng !== null) {
        $this->dispatch('map:set-marker', lat: $this->lat, lng: $this->lng);
    }
}

    protected function loadAddress(int $id): void
    {
        $address = ClientAddress::where('user_id', auth()->id())
            ->findOrFail($id);

        $this->fill([
            'label'         => $address->label,
            'title'         => $address->title,
            'building_type' => $address->building_type ?? 'apartment',

            'search'        => $address->address_text,

            'lat'           => $address->lat,
            'lng'           => $address->lng,
            'place_id'      => $address->place_id,

            'entrance'      => $address->entrance,
            'intercom'      => $address->intercom,
            'floor'         => $address->floor,
            'apartment'     => $address->apartment,

            'city'          => $address->city,
            'street'        => $address->street,
            'house'         => $address->house,
        ]);

        $this->suggestions = [];

        // 👉 координаты передаём в JS,
        // map.js сам поставит маркер, когда карта будет готова
		 if ($this->lat && $this->lng) {
			$this->dispatch('map:set-marker', lat: $this->lat, lng: $this->lng);
		}
    }


    /* =========================================================
     | AUTOCOMPLETE
     |=========================================================*/
public function updatedHouse(): void
{
    // ---------------------------------------------------------
    // 1) Если дом меняется ИЗ КАРТЫ — игнорируем
    //    (programmatic update не считаем ручным вводом)
    // ---------------------------------------------------------
    if ($this->updatingHouseFromMap) {
        return;
    }

    // ---------------------------------------------------------
    // 2) Пользователь реально трогал поле "Дом"
    // ---------------------------------------------------------
    $this->houseTouchedManually = true;

    $house = trim((string) $this->house);
    if ($house === '') {
        return;
    }

    // ---------------------------------------------------------
    // 3) Собираем улицу / город
    // ---------------------------------------------------------
    $street = trim((string) $this->street);
    $city   = trim((string) $this->city);

    // fallback из search
    if ($street === '' && $this->search) {
        $parts = array_map('trim', explode(',', $this->search));
        $street = $parts[0] ?? '';
        if ($city === '' && isset($parts[1])) {
            $city = $parts[1];
        }
    }

    if ($street === '') {
        return;
    }

    // ---------------------------------------------------------
    // 4) Forward-geocode: улица + дом (+ город)
    // ---------------------------------------------------------
    $query = $street . ', ' . $house;
    if ($city !== '') {
        $query .= ', ' . $city;
    }

    try {
        /** @var \App\Services\Geocoding\Geocoder $geocoder */
        $geocoder = app(\App\Services\Geocoding\Geocoder::class);

        // Используем тот метод, который у тебя есть
        // geocode / forward / search / place
        $point = $geocoder->geocode($query);

        if (!empty($point->lat) && !empty($point->lng)) {

            $this->lat = (float) $point->lat;
            $this->lng = (float) $point->lng;

            // -------------------------------------------------
            // 5) Двигаем маркер БЕЗ reverse
            // -------------------------------------------------
            $this->dispatch(
                'map:set-marker',
                lat: $this->lat,
                lng: $this->lng
            );
        }
    } catch (\Throwable $e) {
        // молча — UX важнее
    }
} 

    public function updatedSearch(Geocoder $geocoder): void
    {
        $q = trim((string) $this->search);
        $this->suggestions = [];

        if (mb_strlen($q) < 3) {
            return;
        }

        try {
            $this->suggestions = $geocoder->autocomplete($q);
        } catch (\Throwable $e) {
            $this->suggestions = [];
        }
    }

    public function selectPlace(string $placeId, Geocoder $geocoder): void
    {
        $point = $geocoder->place($placeId);

        $this->place_id = $placeId;
        $this->search   = $point->address;

        // координаты — приблизительные
        $this->lat = $point->lat;
        $this->lng = $point->lng;

        $this->suggestions = [];

        /**
         * 🧠 Авто-определение типа здания
         */
        $components = $point->components ?? [];
        $hasPremise = false;
        $hasSubPremise = false;

        foreach ($components as $c) {
            if (in_array('premise', $c['types'] ?? [], true)) {
                $hasPremise = true;
            }
            if (in_array('subpremise', $c['types'] ?? [], true)) {
                $hasSubPremise = true;
            }
        }

        $this->building_type = (! $hasPremise && ! $hasSubPremise)
            ? 'house'
            : 'apartment';

        // карта
        $this->dispatch('map:set-marker', lat: $this->lat, lng: $this->lng);
    }

    /* =========================================================
     | MAP → FORM
     |=========================================================*/

public function setCoords(float $lat, float $lng, ?string $source = null): void
{
    $this->lat = $lat;
    $this->lng = $lng;

    $this->place_id = null;
    $this->suggestions = [];

    // reverse только если источник — карта
    if ($source !== 'map') {
        return;
    }

    try {
        /** @var \App\Services\Geocoding\Geocoder $geocoder */
        $geocoder = app(\App\Services\Geocoding\Geocoder::class);

        $point = $geocoder->reverse($lat, $lng);

        // 1) строка адреса
        if (!empty($point->address)) {
            $this->search = $point->address;
        }

        // 2) компоненты
        $components = $point->components ?? [];

        $street = null;
        $house  = null;
        $city   = null;

        foreach ($components as $c) {
            $types = $c['types'] ?? [];
            $name  = $c['long_name'] ?? ($c['name'] ?? null);

            if (!$name) continue;

            if (in_array('route', $types, true)) {
                $street = $street ?? $name;
            }

            if (
                in_array('street_number', $types, true) ||
                in_array('house_number', $types, true)
            ) {
                $house = $house ?? $name;
            }

            if (
                in_array('locality', $types, true) ||
                in_array('postal_town', $types, true)
            ) {
                $city = $city ?? $name;
            }
        }

        if ($street) $this->street = $street;
        if ($city)   $this->city   = $city;

        // -------------------------------------------------
        // 3) АВТОЗАПОЛНЕНИЕ ДОМА (ПРАВИЛЬНО)
        // -------------------------------------------------
        if (! $this->houseTouchedManually) {

            // 🔇 тихий режим (чтобы updatedHouse не сработал)
            $this->updatingHouseFromMap = true;

            if ($house) {
                $this->house = $house;
            } elseif ($this->search) {
                // fallback из строки адреса
                if (preg_match(
                    '/,\s*([0-9]+[0-9A-Za-zА-Яа-яІЇЄієї\-\/]*)\b/u',
                    $this->search,
                    $m
                )) {
                    $this->house = $m[1];
                }
            }

            $this->updatingHouseFromMap = false;
        }

    } catch (\Throwable $e) {
        // тихо
    }
}

    /* =========================================================
     | VALIDATION
     |=========================================================*/

    protected function rules(): array
    {
        return [
            'label'         => 'required|in:home,work,other',
            'title'         => 'nullable|string|max:50',
            'building_type' => 'required|in:apartment,house',

            'search' => 'nullable|string|max:255',

            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',

            'city'   => 'nullable|string|min:2|max:80',
            'street' => 'nullable|string|min:2|max:120',
            'house'  => 'nullable|string|max:20',

            'entrance'  => 'nullable|string|max:10',
            'intercom'  => 'nullable|string|max:10',
            'floor'     => 'nullable|string|max:10',
            'apartment' => 'nullable|string|max:10',
        ];
    }

    /* =========================================================
     | SAVE
     |=========================================================*/

public function save(): void
{
	$this->addError('search', 'SAVE CALLED'); // временно
    try {
        // 1) Базовые правила
        $this->validate();

        $isEdit = (bool) $this->addressId;

        // 2) Координаты обязательны всегда
        if ($this->lat === null || $this->lng === null) {
            throw ValidationException::withMessages([
                'search' => 'Уточніть точку на мапі.',
            ]);
        }

        // 3) Fallback: street/city из search
        if (! $this->street && $this->search) {
            $parts = array_map('trim', explode(',', $this->search));
            $this->street = $parts[0] ?? null;

            // не перетираем city, если уже задан
            if (! $this->city && isset($parts[1])) {
                $this->city = $parts[1];
            }
        }

        // 4) Строгие требования — только при создании
        if (! $isEdit) {
			if (! $this->street || ! $this->house) {
				throw ValidationException::withMessages([
					'house' => 'Вкажіть номер будинку.',
				]);
			}

            if ($this->building_type === 'apartment') {
                if (! $this->entrance || ! $this->floor) {
                    throw ValidationException::withMessages([
                        'search' => 'Вкажіть підʼїзд та поверх.',
                    ]);
                }
            }
        }

        $data = [
            'label'         => $this->label,
            'title'         => $this->title,
            'building_type' => $this->building_type,

            'address_text' => $this->search,

            'city'   => $this->city,
            'street' => $this->street,
            'house'  => $this->house,

            'lat' => $this->lat,
            'lng' => $this->lng,

            'entrance'  => $this->building_type === 'apartment' ? $this->entrance : null,
            'intercom'  => $this->building_type === 'apartment' ? $this->intercom : null,
            'floor'     => $this->building_type === 'apartment' ? $this->floor : null,
            'apartment' => $this->building_type === 'apartment' ? $this->apartment : null,

            'geocode_source'   => 'manual',
            'geocode_accuracy' => 'exact',
            'geocoded_at'      => now(),
        ];

        // 5) Save
        if ($isEdit) {
            ClientAddress::where('id', $this->addressId)
                ->where('user_id', auth()->id())
                ->firstOrFail()
                ->update($data);
        } else {
            ClientAddress::create($data + [
                'user_id' => auth()->id(),
            ]);
        }

        // 6) ВАЖНО: Дублируем события “широко”, чтобы точно долетели
        // - одно для UI/JS/всех слушателей
        $this->dispatch('address-saved');

        // - одно конкретно в AddressManager (если у тебя компонент так называется)
        $this->dispatch('address-saved')->to('client.address-manager');

        // 7) Закрытие sheet: тоже делаем максимально совместимо
        $this->dispatch('sheet:close', name: 'editAddress');
        $this->dispatch('sheet:close'); // на случай если твой sheet закрывается без параметров

    } catch (ValidationException $e) {
        // покажет ошибки в форме (как обычно)
        throw $e;

    } catch (\Throwable $e) {
        report($e);

        // чтобы не “молчало”
        $this->addError('search', 'Сталася помилка при збереженні. Перевірте поля та спробуйте ще раз.');

        // лог для отладки (быстро поймешь, где упало)
        Log::error('AddressForm save failed', [
            'addressId' => $this->addressId,
            'user_id' => auth()->id(),
            'message' => $e->getMessage(),
        ]);
    }
}

    /* =========================================================
     | HELPERS
     |=========================================================*/

    protected function resetForm(): void
    {
        $this->reset([
            'addressId',
            'label',
            'title',
            'building_type',
            'search',
            'suggestions',
            'lat',
            'lng',
            'place_id',
            'entrance',
            'intercom',
            'floor',
            'apartment',
            'city',
            'street',
            'house',
        ]);

        $this->label = 'home';
        $this->building_type = 'apartment';
        $this->suggestions = [];
    }

    public function render()
    {
        return view('livewire.client.address-form');
    }
}
