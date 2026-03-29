<?php

namespace App\Livewire\Client\AddressForm\Concerns;

trait HandlesAddressSearchUi
{
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
        $this->summarySearch = null;
        $this->selectedAddressLocked = false;
        $this->resetManualHouseGuard();
        $this->clearSuggestions();
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

        $this->resetManualHouseGuard();
        $this->selectedAddressLocked = true;
        $this->place_id = null;
        $this->search = $this->normalizeSearch($item['label'] ?? $item['line1'] ?? null);
        $this->summarySearch = $this->search;
        $this->lat = isset($item['lat']) ? (float) $item['lat'] : null;
        $this->lng = isset($item['lng']) ? (float) $item['lng'] : null;
        $this->addressPrecision = app(\App\Domain\Address\CoordinateTrustPolicy::class)
            ->precisionForFieldGeocode($this->lat, $this->lng)
            ->value;
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

    protected function clearSuggestions(): void
    {
        $this->suggestions = [];
        $this->activeSuggestionIndex = -1;
        $this->suggestionsMessage = null;
    }
}
