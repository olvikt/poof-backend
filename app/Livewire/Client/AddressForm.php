<?php

namespace App\Livewire\Client;

use App\Actions\Address\PersistClientAddress;
use App\DTO\Address\AddressFieldsData;
use App\DTO\Address\AddressFormData;
use App\DTO\Address\AddressPointData;
use App\DTO\Address\PersistAddressData;
use App\DTO\Address\ResolvedAddressData;
use App\Models\ClientAddress;
use App\Services\Address\FilterClientAddressPayload;
use App\Services\Address\PrepareAddressSavePayload;
use App\Services\Address\ResolveAddressFromPoint;
use App\Services\Address\ResolveAddressPointFromFields;
use App\Support\Address\AddressCoordinatePolicy;
use App\Support\Address\AddressPrecision;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class AddressForm extends Component
{
    public ?int $addressId = null;

    /** apartment | house */
    public string $building_type = 'apartment';

    public string $label = 'home';
    public ?string $title = null;

    public ?string $search = null;
    public bool $isAddressSearchOpen = false;
    public array $suggestions = [];
    public int $activeSuggestionIndex = -1;
    public ?string $suggestionsMessage = null;

    public ?float $lat = null;
    public ?float $lng = null;

    public ?string $place_id = null;

    public ?string $entrance = null;
    public ?string $intercom = null;
    public ?string $floor = null;
    public ?string $apartment = null;

    public ?string $city = null;
    public ?string $region = null;
    public ?string $street = null;
    public ?string $house = null;

    public string $addressPrecision = AddressPrecision::None->value;

    protected bool $houseTouchedManually = false;
    protected bool $updatingHouseFromMap = false;

    protected $listeners = [
        'address:open' => 'open',
        'address:set-coords' => 'setCoords',
    ];

    public function open(?int $addressId = null): void
    {
        $this->addressId = $addressId;

        $addressId
            ? $this->loadAddress($addressId)
            : $this->resetForm();

        $this->dispatch('sheet:open', name: 'addressForm');
        $this->closeAddressSearch();
        $this->syncMarker();
    }

    public function updatedHouse(): void
    {
        if (! AddressCoordinatePolicy::shouldRunHooksForProgrammaticUpdate($this->updatingHouseFromMap)) {
            return;
        }

        $this->houseTouchedManually = true;

        $resolvedPoint = app(ResolveAddressPointFromFields::class)->execute(new AddressFieldsData(
            street: $this->street,
            house: $this->house,
            city: $this->city,
            search: $this->search,
            lat: $this->lat,
            lng: $this->lng,
        ));

        if ($resolvedPoint === null) {
            return;
        }

        if (! AddressCoordinatePolicy::shouldAcceptFieldGeocode(AddressPrecision::fromNullable($this->addressPrecision))) {
            return;
        }

        $this->lat = $resolvedPoint->lat;
        $this->lng = $resolvedPoint->lng;
        $this->addressPrecision = AddressCoordinatePolicy::precisionForFieldGeocode($this->lat, $this->lng)->value;

        $this->syncMarker();
    }

    public function updatedSearch($value = null): void
    {
        $normalizedValue = $this->normalizeSearch($value ?? $this->search);

        if ($this->search !== $normalizedValue) {
            $this->search = $normalizedValue;
        }

        if (mb_strlen(trim((string) $this->search)) < 3) {
            $this->clearSuggestions();
        }
    }

    public function openAddressSearch(): void
    {
        $this->isAddressSearchOpen = true;
    }

    public function closeAddressSearch(): void
    {
        $this->isAddressSearchOpen = false;
        $this->clearSuggestions();
    }

    public function clearSearch(): void
    {
        $this->search = null;
        $this->clearSuggestions();
    }

    public function toggleBuildingType(): void
    {
        $this->building_type = $this->building_type === 'house'
            ? 'apartment'
            : 'house';
    }

    public function setPhotonSuggestions($items, $message = null): void
    {
        $this->suggestions = is_array($items)
            ? collect($items)
                ->map(function ($item): ?array {
                    if (! is_array($item)) {
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
        $this->suggestionsMessage = is_string($message) && trim($message) !== ''
            ? trim($message)
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

        if (! is_array($item)) {
            return;
        }

        $this->place_id = null;
        $this->search = $this->normalizeSearch($item['label'] ?? $item['line1'] ?? null);
        $this->lat = isset($item['lat']) ? (float) $item['lat'] : null;
        $this->lng = isset($item['lng']) ? (float) $item['lng'] : null;
        $this->addressPrecision = AddressCoordinatePolicy::precisionForFieldGeocode($this->lat, $this->lng)->value;
        $this->street = $item['street'] ?? $this->street;
        $this->house = $item['house'] ?? $this->house;
        $this->city = $item['city'] ?? $this->city;
        $this->region = $item['region'] ?? $this->region;

        $this->isAddressSearchOpen = false;
        $this->clearSuggestions();

        if ($this->lat !== null && $this->lng !== null) {
            $this->syncMarker();
            $this->dispatch('map:set-location', lat: $this->lat, lng: $this->lng, source: 'autocomplete', zoom: 17);
            $this->dispatch('map:update', lat: $this->lat, lng: $this->lng, zoom: 17);
        }
    }

    public function setCoords(float $lat, float $lng, ?string $source = null): void
    {
        $this->lat = $lat;
        $this->lng = $lng;
        $this->addressPrecision = $source === 'map'
            ? AddressCoordinatePolicy::precisionForManualPointSelection($lat, $lng)->value
            : AddressCoordinatePolicy::precisionForFieldGeocode($lat, $lng)->value;
        $this->place_id = null;
        $this->clearSuggestions();

        if ($source !== 'map') {
            return;
        }

        $resolved = app(ResolveAddressFromPoint::class)->execute(new AddressPointData(
            lat: $lat,
            lng: $lng,
            source: $source,
        ));

        if ($resolved !== null) {
            $this->applyResolvedAddress($resolved);
        }
    }

    protected function rules(): array
    {
        return [
            'label' => 'required|in:home,work,other',
            'title' => 'nullable|string|max:50',
            'building_type' => 'required|in:apartment,house',
            'search' => 'nullable|string|max:255',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'city' => 'required|string|max:80',
            'region' => 'nullable|string|max:120',
            'street' => 'required|string|min:2|max:120',
            'house' => 'required|string|max:20',
            'entrance' => 'nullable|string|max:10',
            'floor' => 'nullable|string|max:10',
            'intercom' => 'nullable|string|max:10',
            'apartment' => 'nullable|string|max:10',
        ];
    }

    public function save(): void
    {
        try {
            $formData = AddressFormData::fromComponent($this);
            $payloadPreparer = app(PrepareAddressSavePayload::class);

            foreach ($payloadPreparer->applyFallback($formData) as $field => $value) {
                $this->{$field} = $value;
            }

            $this->validate();
            $this->ensureCoordinatesArePresent();

            $formData = AddressFormData::fromComponent($this);
            $payload = $payloadPreparer->execute($formData);
            $filteredPayload = app(FilterClientAddressPayload::class)->execute($payload->toArray());

            app(PersistClientAddress::class)->execute(
                $formData,
                new PersistAddressData($filteredPayload),
                auth()->id(),
            );

            $this->dispatch('address-saved');
            $this->dispatch('address-saved')->to('client.address-manager');
            $this->dispatch('sheet:close', name: 'addressForm');
            $this->dispatch('sheet:close');
        } catch (ValidationException $e) {
            Log::error('Address save failed', [
                'user_id' => auth()->id(),
                'payload' => $this->payloadForLogs(),
                'errors' => $e->errors(),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            report($e);

            $this->addError('search', 'Сталася помилка при збереженні. Перевірте поля та спробуйте ще раз.');

            Log::error('Address save exception', [
                'user_id' => auth()->id(),
                'payload' => $this->payloadForLogs(),
                'errors' => $this->getErrorBag()->toArray(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.client.address-form');
    }

    protected function loadAddress(int $id): void
    {
        $address = ClientAddress::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $this->fill([
            'label' => $address->label,
            'title' => $address->title,
            'building_type' => $address->building_type ?? 'apartment',
            'search' => $this->normalizeSearch($address->address_text),
            'lat' => $address->lat,
            'lng' => $address->lng,
            'place_id' => $address->place_id,
            'entrance' => $address->entrance,
            'intercom' => $address->intercom,
            'floor' => $address->floor,
            'apartment' => $address->apartment,
            'city' => $address->city,
            'region' => $address->region,
            'street' => $address->street,
            'house' => $address->house,
            'addressPrecision' => AddressCoordinatePolicy::precisionForAddressBook($address->lat, $address->lng)->value,
        ]);

        $this->clearSuggestions();
        $this->houseTouchedManually = false;
        $this->updatingHouseFromMap = false;
    }

    protected function applyResolvedAddress(ResolvedAddressData $resolved): void
    {
        if ($resolved->street) {
            $this->street = $resolved->street;
        }

        if ($resolved->city) {
            $this->city = $resolved->city;
        }

        if ($resolved->region) {
            $this->region = $resolved->region;
        }

        $this->search = $resolved->search;

        if (! AddressCoordinatePolicy::shouldReverseFillHouse($this->houseTouchedManually)) {
            return;
        }

        $this->updatingHouseFromMap = true;

        if ($resolved->house) {
            $this->house = $resolved->house;
        }

        $this->updatingHouseFromMap = false;
    }

    protected function ensureCoordinatesArePresent(): void
    {
        if ($this->lat !== null && $this->lng !== null) {
            return;
        }

        throw ValidationException::withMessages([
            'search' => 'Уточніть точку на мапі.',
        ]);
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

    protected function resetForm(): void
    {
        $this->reset([
            'addressId',
            'label',
            'title',
            'building_type',
            'search',
            'isAddressSearchOpen',
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
            'addressPrecision',
        ]);

        $this->label = 'home';
        $this->building_type = 'apartment';
        $this->addressPrecision = AddressPrecision::None->value;
        $this->clearSuggestions();
        $this->houseTouchedManually = false;
        $this->updatingHouseFromMap = false;
    }

    protected function clearSuggestions(): void
    {
        $this->suggestions = [];
        $this->activeSuggestionIndex = -1;
        $this->suggestionsMessage = null;
    }

    protected function syncMarker(): void
    {
        if ($this->lat === null || $this->lng === null) {
            return;
        }

        $this->dispatch('map:set-marker', lat: $this->lat, lng: $this->lng);
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
}
