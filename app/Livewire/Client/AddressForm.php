<?php

namespace App\Livewire\Client;

use Livewire\Component;
use App\Models\ClientAddress;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
    public int $activeSuggestionIndex = -1;
    public ?string $suggestionsMessage = null;

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
    public ?string $region = null;
    public ?string $street = null;
    public ?string $house = null;
	
	 // -----------------------------
    // INTERNAL FLAGS (НЕ UI)
    // -----------------------------
    protected bool $houseTouchedManually = false;
    protected bool $updatingHouseFromMap = false;

    protected ?array $addressColumns = null;
	

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
    $this->dispatch('sheet:open', name: 'addressForm');

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

            'search'        => $this->normalizeSearch($address->address_text),

            'lat'           => $address->lat,
            'lng'           => $address->lng,
            'place_id'      => $address->place_id,

            'entrance'      => $address->entrance,
            'intercom'      => $address->intercom,
            'floor'         => $address->floor,
            'apartment'     => $address->apartment,

            'city'          => $address->city,
            'region'        => $address->region,
            'street'        => $address->street,
            'house'         => $address->house,
        ]);

        $this->suggestions = [];
        $this->activeSuggestionIndex = -1;
        $this->suggestionsMessage = null;

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
        $response = Http::timeout(8)
            ->acceptJson()
            ->get(url('/api/geocode'), [
                'q' => $query,
                'lat' => $this->lat,
                'lng' => $this->lng,
            ]);

        if (! $response->successful()) {
            return;
        }

        $item = $response->json('0');
        if (!is_array($item)) {
            return;
        }

        if (!isset($item['lat'], $item['lng'])) {
            return;
        }

        $this->lat = (float) $item['lat'];
        $this->lng = (float) $item['lng'];

        $this->dispatch('map:set-marker', lat: $this->lat, lng: $this->lng);
    } catch (\Throwable $e) {
        // молча — UX важнее
    }
} 

    public function updatedSearch($value = null): void
    {
        $normalizedValue = $this->normalizeSearch($value ?? $this->search);

        if ($this->search !== $normalizedValue) {
            $this->search = $normalizedValue;
        }

        if (mb_strlen(trim((string) $this->search)) < 3) {
            $this->suggestions = [];
            $this->activeSuggestionIndex = -1;
            $this->suggestionsMessage = null;
        }
    }

    public function setPhotonSuggestions($items, $message = null): void
    {
        $this->suggestions = is_array($items)
            ? collect($items)
                ->map(function ($item): ?array {
                    if (!is_array($item)) {
                        return null;
                    }

                    $lat = isset($item['lat']) ? (float) $item['lat'] : null;
                    $lng = isset($item['lng']) ? (float) $item['lng'] : null;

                    if ($lat === null || $lng === null) {
                        return null;
                    }

                    return [
                        'lat' => $lat,
                        'lng' => $lng,
                        'street' => isset($item['street']) ? trim((string) $item['street']) : null,
                        'house' => isset($item['house']) ? trim((string) $item['house']) : null,
                        'city' => isset($item['city']) ? trim((string) $item['city']) : null,
                        'region' => isset($item['region']) ? trim((string) $item['region']) : null,
                        'line1' => isset($item['line1']) ? trim((string) $item['line1']) : null,
                        'line2' => isset($item['line2']) ? trim((string) $item['line2']) : null,
                        'label' => isset($item['label']) ? trim((string) $item['label']) : null,
                    ];
                })
                ->filter()
                ->values()
                ->all()
            : [];

        $this->activeSuggestionIndex = -1;
        $this->suggestionsMessage = is_string($message) || is_null($message)
            ? (is_string($message) && trim($message) !== '' ? trim($message) : null)
            : null;
    }

    public function moveSuggestionDown(): void
    {
        $count = count($this->suggestions);

        if ($count === 0) {
            $this->activeSuggestionIndex = -1;

            return;
        }

        $this->activeSuggestionIndex = ($this->activeSuggestionIndex + 1) % $count;
    }

    public function moveSuggestionUp(): void
    {
        $count = count($this->suggestions);

        if ($count === 0) {
            $this->activeSuggestionIndex = -1;

            return;
        }

        if ($this->activeSuggestionIndex <= 0) {
            $this->activeSuggestionIndex = $count - 1;

            return;
        }

        $this->activeSuggestionIndex--;
    }

    public function selectActiveSuggestion(): void
    {
        if ($this->activeSuggestionIndex >= 0) {
            $this->selectSuggestion($this->activeSuggestionIndex);
        }
    }

    public function selectSuggestion(int $index): void
    {
        $item = $this->suggestions[$index] ?? null;
        if (!is_array($item)) {
            return;
        }

        $this->place_id = null;
        $this->search = $this->normalizeSearch($item['label'] ?? $item['line1'] ?? null);

        $this->lat = isset($item['lat']) ? (float) $item['lat'] : null;
        $this->lng = isset($item['lng']) ? (float) $item['lng'] : null;

        $this->street = $item['street'] ?? $this->street;
        $this->house = $item['house'] ?? $this->house;
        $this->city = $item['city'] ?? $this->city;
        $this->region = $item['region'] ?? $this->region;

        $this->suggestions = [];
        $this->activeSuggestionIndex = -1;
        $this->suggestionsMessage = null;

        if ($this->lat !== null && $this->lng !== null) {
            $this->dispatch('map:set-location', lat: $this->lat, lng: $this->lng, source: 'autocomplete', zoom: 17);
            $this->dispatch('map:update', lat: $this->lat, lng: $this->lng, zoom: 17);
        }
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
    $this->activeSuggestionIndex = -1;
    $this->suggestionsMessage = null;

    // reverse только если источник — карта
    if ($source !== 'map') {
        return;
    }

        try {
        $result = Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => config('app.name', 'Poof') . '/1.0',
            ])
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'json',
                'lat' => $lat,
                'lon' => $lng,
                'addressdetails' => 1,
            ]);

        if (! $result->successful()) {
            return;
        }

        $payload = $result->json();

        if (!is_array($payload)) {
            return;
        }

        $address = $payload['address'] ?? [];
        $street = $this->normalizeStreet(
            $address['road'] ?? $address['pedestrian'] ?? $address['street'] ?? null
        );
        $house  = $this->normalizeHouse($address['house_number'] ?? null);
        $city   = $address['city'] ?? $address['town'] ?? $address['village'] ?? null;
        $region = $address['state'] ?? $address['region'] ?? null;

        if ($street) {
            $this->street = $street;
        }

        if ($city) {
            $this->city = trim((string) $city);
        }

        if ($region) {
            $this->region = trim((string) $region);
        }

        $line1 = trim(implode(' ', array_filter([$street, $house])));
        $line2 = trim(implode(', ', array_filter([$this->city, $this->region])));
        $this->search = $this->normalizeSearch($payload['label'] ?? trim(implode(', ', array_filter([$line1, $line2]))));

        // -------------------------------------------------
        // 3) АВТОЗАПОЛНЕНИЕ ДОМА (ПРАВИЛЬНО)
        // -------------------------------------------------
        if (! $this->houseTouchedManually) {

            // 🔇 тихий режим (чтобы updatedHouse не сработал)
            $this->updatingHouseFromMap = true;

            if ($house) {
                $this->house = $house;
            }

            if (! $this->house && ! empty($payload['display_name'])) {
                // fallback из строки адреса
                if (preg_match(
                    '/,\s*([0-9]+[0-9A-Za-zА-Яа-яІЇЄієї\-\/]*)\b/u',
                    (string) $payload['display_name'],
                    $m
                )) {
                    $this->house = $this->normalizeHouse($m[1]);
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

        'lat' => 'required|numeric|between:-90,90',
        'lng' => 'required|numeric|between:-180,180',

        'city'   => 'required|string|max:80',
        'region' => 'nullable|string|max:120',
        'street' => 'required|string|min:2|max:120',

        'house' => 'required|string|max:20',

        'entrance' => 'nullable|string|max:10',

        'floor' => 'nullable|string|max:10',

        'intercom'  => 'nullable|string|max:10',
        'apartment' => 'nullable|string|max:10',
    ];
}

    /* =========================================================
     | SAVE
     |=========================================================*/

public function save(): void
{

    try {
        $isEdit = (bool) $this->addressId;

        // 1) Fallback: street/city из search
        if (! $this->street && $this->search) {
            $parts = array_map('trim', explode(',', $this->search));
            $this->street = $this->normalizeStreet($parts[0] ?? null);

            // не перетираем city, если уже задан
            if (! $this->city && isset($parts[1])) {
                $this->city = $parts[1];
            }
        }

        // 2) Базовые правила
        $this->validate();

        // 3) Координаты обязательны всегда
        if ($this->lat === null || $this->lng === null) {
            throw ValidationException::withMessages([
                'search' => 'Уточніть точку на мапі.',
            ]);
        }

        $payload = [
            'label'         => $this->label,
            'title'         => $this->title,
            'building_type' => $this->building_type,

            'address_text' => $this->search,

            'city'   => $this->city,
            'region' => $this->region,
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

        $data = $this->filterPersistedPayload($payload);

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
        $this->dispatch('sheet:close', name: 'addressForm');
        $this->dispatch('sheet:close'); // на случай если твой sheet закрывается без параметров

    } catch (ValidationException $e) {
        Log::error('Address save failed', [
            'user_id' => auth()->id(),
            'payload' => $this->payloadForLogs(),
            'errors' => $e->errors(),
        ]);

        // покажет ошибки в форме (как обычно)
        throw $e;

    } catch (\Throwable $e) {
        report($e);

        // чтобы не “молчало”
        $this->addError('search', 'Сталася помилка при збереженні. Перевірте поля та спробуйте ще раз.');

        // лог для отладки (быстро поймешь, где упало)
        Log::error('Address save exception', [
            'user_id' => auth()->id(),
            'payload' => $this->payloadForLogs(),
            'errors' => $this->getErrorBag()->toArray(),
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}

    protected function payloadForLogs(): array
    {
        return [
            'addressId' => $this->addressId,
            'label' => $this->label,
            'title' => $this->title,
            'building_type' => $this->building_type,
            'search' => $this->search,
            'city' => $this->city,
            'region' => $this->region,
            'street' => $this->street,
            'house' => $this->house,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'entrance' => $this->entrance,
            'intercom' => $this->intercom,
            'floor' => $this->floor,
            'apartment' => $this->apartment,
        ];
    }

    protected function filterPersistedPayload(array $payload): array
    {
        $columns = $this->addressColumns ??= Schema::getColumnListing('client_addresses');

        return collect($payload)
            ->only($columns)
            ->all();
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
            'activeSuggestionIndex',
            'suggestionsMessage',
            'lat',
            'lng',
            'place_id',
            'entrance',
            'intercom',
            'floor',
            'apartment',
            'city',
            'region',
            'street',
            'house',
        ]);

        $this->label = 'home';
        $this->building_type = 'apartment';
        $this->suggestions = [];
        $this->activeSuggestionIndex = -1;
        $this->suggestionsMessage = null;
    }

    protected function normalizeStreet(?string $street): ?string
    {
        $street = trim((string) $street);
        if ($street === '') {
            return null;
        }

        return preg_replace('/^\s*\d+[\dA-Za-zА-Яа-яІЇЄієї\-\/]*\s*,\s*/u', '', $street) ?: null;
    }

    protected function normalizeHouse(?string $house): ?string
    {
        $house = trim((string) $house);

        return $house !== '' ? $house : null;
    }

    protected function normalizeSearch($value): string
    {
        if (is_array($value)) {
            return trim((string) ($value['label'] ?? $value['name'] ?? ''));
        }

        if (is_object($value)) {
            return trim((string) ($value->label ?? $value->name ?? ''));
        }

        return trim((string) $value);
    }

    public function render()
    {
        return view('livewire.client.address-form');
    }
}
