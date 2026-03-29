<?php

namespace App\Livewire\Client\OrderCreate\Concerns;

use App\Models\ClientAddress;
use App\Support\Address\AddressCoordinatePolicy;
use App\Support\Address\AddressPrecision;
use Livewire\Attributes\On;

trait HandlesAddressSelection
{
    protected function loadAddressFromBook(int $addressId): void
    {
        $address = ClientAddress::where('id', $addressId)
            ->where('user_id', auth()->id())
            ->first();

        if (! $address) {
            return;
        }

        $this->suppressAddressHooks = true;

        $this->coordsFromAddressBook = true;
        $this->address_precision = AddressCoordinatePolicy::precisionForAddressBook($address->lat, $address->lng)->value;
        $this->address_text = $address->address_text ?? $address->full_address;
        $this->street = $address->street;
        $this->house = $address->house;
        $this->city = $address->city;
        $this->lat = $address->lat;
        $this->lng = $address->lng;
        $this->entrance = $address->entrance;
        $this->floor = $address->floor;
        $this->apartment = $address->apartment;
        $this->intercom = $address->intercom;

        $this->suppressAddressHooks = false;

        if ($this->lat && $this->lng) {
            $this->dispatch('map:set-marker', lat: $this->lat, lng: $this->lng);
            $this->dispatch('map:set-marker-precision', precision: AddressPrecision::Exact->value);
        }
    }

    protected function hydrateFromOrder(\App\Models\Order $order): void
    {
        $this->suppressAddressHooks = true;

        try {
            if ($order->address_id) {
                $this->loadAddressFromBook($order->address_id);
                return;
            }

            $this->address_id = null;
            $this->address_text = $order->address_text ?? '';
            $this->street = null;
            $this->house = null;
            $this->city = null;
            $this->entrance = $order->entrance;
            $this->floor = $order->floor;
            $this->apartment = $order->apartment;
            $this->intercom = $order->intercom;
            $this->lat = $order->lat;
            $this->lng = $order->lng;
            $this->coordsFromAddressBook = true;
            $this->address_precision = AddressCoordinatePolicy::precisionForAddressBook($this->lat, $this->lng)->value;
        } finally {
            $this->suppressAddressHooks = false;
        }

        if (! $order->address_id && $this->lat && $this->lng) {
            $this->hydrateAddressFromCoords($this->lat, $this->lng);
        }

        if ($this->lat && $this->lng) {
            $this->dispatch('map:set-marker', lat: $this->lat, lng: $this->lng);
            $this->dispatch('map:set-marker-precision', precision: AddressPrecision::Approx->value);
        }
    }

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
            $this->house = $address->house;
            $this->city = $address->city;
            $this->syncAddressText();
            $this->entrance = $address->entrance;
            $this->floor = $address->floor;
            $this->apartment = $address->apartment;
            $this->intercom = $address->intercom;
            $this->lat = $address->lat;
            $this->lng = $address->lng;
            $this->coordsFromAddressBook = true;
            $this->address_precision = AddressCoordinatePolicy::precisionForAddressBook($this->lat, $this->lng)->value;
            $this->geocodeToken = null;
        } finally {
            $this->suppressAddressHooks = false;
        }

        if (AddressPrecision::fromNullable($this->address_precision)->isExact()) {
            $this->pushMarkerToMap();
        }

        $this->dispatch('sheet:close', name: 'addressPicker');
    }

    protected function syncAddressText(): void
    {
        $this->address_text = trim(
            collect([$this->street, $this->house])
                ->filter(fn ($v) => filled($v))
                ->implode(' ')
        );
    }

    protected function syncStreetFromAddressText(): void
    {
        if (! filled($this->address_text)) {
            return;
        }

        $parts = array_map('trim', explode(',', $this->address_text));
        $this->street = $parts[0] ?? $this->street;

        if (! filled($this->city) && isset($parts[1])) {
            $this->city = $parts[1];
        }
    }

    public function updatedAddressText(): void
    {
        if (! AddressCoordinatePolicy::shouldRunHooksForProgrammaticUpdate($this->suppressAddressHooks)) {
            return;
        }

        $this->address_id = null;
        $this->coordsFromAddressBook = false;
        $this->address_precision = AddressPrecision::None->value;

        $this->syncStreetFromAddressText();
    }

    public function updatedStreet(): void
    {
        if (! AddressCoordinatePolicy::shouldRunHooksForProgrammaticUpdate($this->suppressAddressHooks)) {
            return;
        }

        $this->coordsFromAddressBook = false;
        $this->address_id = null;
        $this->address_precision = AddressPrecision::None->value;

        $this->syncAddressText();
    }

    public function updatedHouse(): void
    {
        if (! AddressCoordinatePolicy::shouldRunHooksForProgrammaticUpdate($this->suppressAddressHooks)) {
            return;
        }

        $this->coordsFromAddressBook = false;
        $this->address_id = null;
        $this->address_precision = AddressPrecision::None->value;
        $this->syncAddressText();
        $this->scheduleGeocode();
    }
}
