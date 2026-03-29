<?php

namespace App\Livewire\Client\AddressForm\Concerns;

use App\DTO\Address\AddressFieldsData;
use App\DTO\Address\AddressPointData;
use App\DTO\Address\ResolvedAddressData;
use App\Domain\Address\CoordinateTrustPolicy;
use App\Domain\Address\MarkerSyncContract;
use App\Domain\Address\Precision;
use App\Services\Address\ResolveAddressFromPoint;
use App\Services\Address\ResolveAddressPointFromFields;

trait HandlesAddressPointResolution
{
    public function updatedHouse(): void
    {
        if (! app(CoordinateTrustPolicy::class)->shouldRunHooksForProgrammaticUpdate($this->updatingHouseFromMap)) {
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

        if (! app(CoordinateTrustPolicy::class)->shouldAcceptFieldGeocode(Precision::fromNullable($this->addressPrecision))) {
            return;
        }

        $this->lat = $resolvedPoint->lat;
        $this->lng = $resolvedPoint->lng;
        $this->addressPrecision = app(CoordinateTrustPolicy::class)->precisionForFieldGeocode($this->lat, $this->lng)->value;

        $this->syncMarker();
    }

    public function setCoords(float $lat, float $lng, ?string $source = null): void
    {
        if (! app(MarkerSyncContract::class)->shouldAcceptIncomingSource($source)) {
            return;
        }

        if ($this->shouldIgnoreIncomingCoords($lat, $lng, $source)) {
            return;
        }

        $this->lat = $lat;
        $this->lng = $lng;
        $this->addressPrecision = app(MarkerSyncContract::class)
            ->precisionForIncomingSource($lat, $lng, $source, app(CoordinateTrustPolicy::class))
            ->value;
        $this->place_id = null;
        $this->clearSuggestions();

        $this->selectedAddressLocked = false;

        $resolved = app(ResolveAddressFromPoint::class)->execute(new AddressPointData(
            lat: $lat,
            lng: $lng,
            source: $source,
        ));

        if ($resolved !== null) {
            $this->applyResolvedAddress($resolved);
        }
    }

    protected function shouldIgnoreIncomingCoords(float $lat, float $lng, ?string $source): bool
    {
        return app(CoordinateTrustPolicy::class)->shouldIgnoreIncomingCoords(
            $this->lat,
            $this->lng,
            Precision::fromNullable($this->addressPrecision),
            $this->selectedAddressLocked,
            $lat,
            $lng,
            $source,
        );
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
        $this->summarySearch = $resolved->search;

        if (! app(CoordinateTrustPolicy::class)->shouldReverseFillHouse($this->houseTouchedManually)) {
            return;
        }

        $this->updatingHouseFromMap = true;

        if ($resolved->house) {
            $this->house = $resolved->house;
        }

        $this->updatingHouseFromMap = false;
    }

    protected function syncMarker(): void
    {
        if ($this->lat === null || $this->lng === null) {
            return;
        }

        $this->dispatch('map:set-marker', lat: $this->lat, lng: $this->lng);
        $this->dispatch('map:set-marker-precision', precision: $this->addressPrecision);
    }

    protected function hasExistingPoint(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
