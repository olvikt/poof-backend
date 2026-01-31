<?php

namespace App\Livewire\Client;

use Livewire\Component;
use App\Models\ClientAddress;
use App\Services\Geocoding\Geocoder;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AddressForm extends Component
{
    /* =========================================================
     | STATE
     |=========================================================*/

    public ?int $addressId = null;

    public string $label = 'home';
    public ?string $title = null;

    // UI / UX
    public ?string $search = null;
    public array $suggestions = [];

    // Координаты (ИСТИНА)
    public ?float $lat = null;
    public ?float $lng = null;

    // Google meta
    public ?string $place_id = null;

    // Детали
    public ?string $entrance = null;
    public ?string $intercom = null;
    public ?string $floor = null;
    public ?string $apartment = null;

    // Адрес
    public ?string $city = null;
    public ?string $street = null;
    public ?string $house = null;

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

        $addressId
            ? $this->loadAddress($addressId)
            : $this->resetForm();

        $this->dispatch('sheet:open', name: 'editAddress');
    }

    protected function loadAddress(int $id): void
    {
        $address = ClientAddress::where('user_id', auth()->id())
            ->findOrFail($id);

        $this->fill([
            'label'     => $address->label,
            'title'     => $address->title,

            'search'    => $address->address_text,

            'lat'       => $address->lat,
            'lng'       => $address->lng,
            'place_id'  => $address->place_id,

            'entrance'  => $address->entrance,
            'intercom'  => $address->intercom,
            'floor'     => $address->floor,
            'apartment' => $address->apartment,

            'city'      => $address->city,
            'street'    => $address->street,
            'house'     => $address->house,
        ]);

        $this->suggestions = [];

        if ($this->lat && $this->lng) {
            $this->dispatch('map:set-point', lat: $this->lat, lng: $this->lng);
        }
    }

    /* =========================================================
     | AUTOCOMPLETE (UX only)
     |=========================================================*/

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

        // UX
        $this->place_id = $placeId;
        $this->search   = $point->address;

        // Временная точка (улица / район)
        $this->lat = $point->lat;
        $this->lng = $point->lng;

        $this->suggestions = [];

        $this->dispatch('map:set-point', lat: $this->lat, lng: $this->lng);
    }

    /* =========================================================
     | MAP → FORM
     |=========================================================*/

    public function setCoords(
        float $lat,
        float $lng,
        ?bool $reverse = true,
        ?string $source = null,
        Geocoder $geocoder = null
    ): void {
        $this->lat = $lat;
        $this->lng = $lng;
        $this->place_id = null;
        $this->suggestions = [];

        if ($reverse && $geocoder) {
            try {
                $point = $geocoder->reverse($lat, $lng);
                $this->search = $point->address ?? $this->search;
            } catch (\Throwable $e) {}
        }
    }
	
	
	/* =========================================================
	 | VALIDATION
	 |=========================================================*/

	protected function rules(): array
	{
		return [
			// тип адреса
			'label' => 'required|string|in:home,work,other',
			'title' => 'nullable|string|max:50',

			// UI строка
			'search' => 'nullable|string|max:255',

			// координаты = истина
			'lat' => 'nullable|numeric|between:-90,90',
			'lng' => 'nullable|numeric|between:-180,180',

			// адрес
			'city'   => 'nullable|string|min:2|max:80',
			'street' => 'nullable|string|min:2|max:120',
			'house'  => 'nullable|string|max:20',

			// детали
			'entrance'  => 'nullable|string|max:10',
			'intercom'  => 'nullable|string|max:10',
			'floor'     => 'nullable|string|max:10',
			'apartment' => 'nullable|string|max:10',
		];
	}

    /* =========================================================
     | SAVE (КЛЮЧЕВОЕ МЕСТО)
     |=========================================================*/

	public function save(): void
	{
		$this->validate();

		/**
		 * 1️⃣ Координаты обязательны
		 * (либо из Google, либо пользователь указал сам)
		 */
		if ($this->lat === null || $this->lng === null) {
			throw ValidationException::withMessages([
				'search' => 'Уточніть точку на мапі.',
			]);
		}

		/**
		 * 2️⃣ Восстановление street / city из search (fallback)
		 */
		if (! $this->street && $this->search) {
			$parts = array_map('trim', explode(',', $this->search));
			$this->street = $parts[0] ?? null;

			if (! $this->city && isset($parts[1])) {
				$this->city = $parts[1];
			}
		}

		/**
		 * 3️⃣ Улица и дом обязательны
		 */
		if (! $this->street || ! $this->house) {
			throw ValidationException::withMessages([
				'search' => 'Вкажіть вулицю та номер будинку.',
			]);
		}

		/**
		 * 4️⃣ Попытка УЛУЧШИТЬ координаты по дому
		 * (НЕ затираем существующие, если Google не дал лучше)
		 */
		$originalLat = $this->lat;
		$originalLng = $this->lng;

		$this->geocodeStreetHouseHard();

		// если геокодинг не дал нового результата — возвращаем старый
		if ($this->lat === null || $this->lng === null) {
			$this->lat = $originalLat;
			$this->lng = $originalLng;
		}

		/**
		 * 5️⃣ Формируем данные
		 */
		$data = [
			// тип адреса
			'label'   => $this->label,
			'title'   => $this->title,

			// UI (подпись)
			'address_text' => $this->search,

			// адрес
			'city'   => $this->city,
			'street' => $this->street,
			'house'  => $this->house,

			// ИСТИНА
			'lat' => $this->lat,
			'lng' => $this->lng,

			// геокодинг-мета
			'place_id'         => null,
			'geocode_source'   => 'street_house',
			'geocode_accuracy' => 'approximate', // честно
			'geocoded_at'      => now(),

			// детали
			'entrance'  => $this->entrance,
			'intercom'  => $this->intercom,
			'floor'     => $this->floor,
			'apartment' => $this->apartment,
		];

		/**
		 * 6️⃣ Save / Update
		 */
		if ($this->addressId) {
			ClientAddress::where('id', $this->addressId)
				->where('user_id', auth()->id())
				->firstOrFail()
				->update($data);
		} else {
			ClientAddress::create($data + [
				'user_id' => auth()->id(),
			]);
		}

		/**
		 * 7️⃣ UI events
		 */
		$this->dispatch('address-saved')
			->to('client.address-manager');

		$this->dispatch('sheet:close', name: 'editAddress');

		$this->resetForm();
	}

    /* =========================================================
     | HARD ROOFTOP GEOCODE (без сюрпризов)
     |=========================================================*/

    protected function geocodeStreetHouseHard(): void
    {
        $query = trim("{$this->city}, {$this->street} {$this->house}");

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

            $result = $response->json('results.0');
            if (! $result) {
                return;
            }

            $type = $result['geometry']['location_type'] ?? null;

			// ❌ только если совсем мусор
			if ($type === 'APPROXIMATE') {
				return;
			}

            $this->lat = (float) $result['geometry']['location']['lat'];
            $this->lng = (float) $result['geometry']['location']['lng'];

        } catch (\Throwable $e) {
            // silent fail
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
        $this->suggestions = [];
    }

    public function render()
    {
        return view('livewire.client.address-form');
    }
}


