<?php

namespace App\Livewire\Client;

use App\Domain\Address\AddressParser;
use App\Domain\Address\CoordinateTrustPolicy;
use App\Domain\Address\Precision;
use App\Livewire\Client\AddressForm\Concerns\HandlesAddressPersistence;
use App\Livewire\Client\AddressForm\Concerns\HandlesAddressPointResolution;
use App\Livewire\Client\AddressForm\Concerns\HandlesAddressSearchUi;
use App\Models\ClientAddress;
use Livewire\Component;

class AddressForm extends Component
{
    use HandlesAddressSearchUi;
    use HandlesAddressPointResolution;
    use HandlesAddressPersistence;

    public ?int $addressId = null;

    /** apartment | house */
    public string $building_type = 'apartment';

    public string $label = 'home';
    public ?string $title = null;

    public ?string $search = null;
    public ?string $summarySearch = null;
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

    public string $addressPrecision = Precision::None->value;

    public bool $houseTouchedManually = false;
    public bool $selectedAddressLocked = false;
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

    public function toggleBuildingType(): void
    {
        $this->building_type = $this->building_type === 'house'
            ? 'apartment'
            : 'house';
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

        $this->selectedAddressLocked = true;

        $this->fill([
            'label' => $address->label,
            'title' => $address->title,
            'building_type' => $address->building_type ?? 'apartment',
            'search' => $this->normalizeSearch($address->address_text),
            'summarySearch' => $this->normalizeSearch($address->address_text),
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
            'addressPrecision' => app(CoordinateTrustPolicy::class)->precisionForAddressBook($address->lat, $address->lng)->value,
        ]);

        $this->clearSuggestions();
        $this->resetManualHouseGuard();
        $this->updatingHouseFromMap = false;
    }

    protected function resetForm(): void
    {
        $this->reset([
            'addressId',
            'label',
            'title',
            'building_type',
            'search',
            'summarySearch',
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
        $this->selectedAddressLocked = false;
        $this->addressPrecision = Precision::None->value;
        $this->clearSuggestions();
        $this->resetManualHouseGuard();
        $this->updatingHouseFromMap = false;
    }

    protected function resetManualHouseGuard(): void
    {
        $this->houseTouchedManually = false;
    }

    protected function normalizeSearch($value): string
    {
        return app(AddressParser::class)->normalizeSearch($value);
    }
}
