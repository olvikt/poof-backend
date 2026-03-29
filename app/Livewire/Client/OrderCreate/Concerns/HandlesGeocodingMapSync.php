<?php

namespace App\Livewire\Client\OrderCreate\Concerns;

use App\Services\Geocoding\Geocoder;
use App\Support\Address\AddressCoordinatePolicy;
use App\Support\Address\AddressPrecision;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;

trait HandlesGeocodingMapSync
{
    #[On('set-location')]
    public function setLocation(float $lat, float $lng): void
    {
        $this->lat = $lat;
        $this->lng = $lng;

        $this->address_precision = AddressCoordinatePolicy::precisionForManualPointSelection($lat, $lng)->value;
        $this->coordsFromAddressBook = false;
        $this->address_id = null;

        $this->reverseGeocodeFromPoint($lat, $lng);
    }

    protected function pushMarkerToMap(): void
    {
        if ($this->lat === null || $this->lng === null) {
            return;
        }

        $this->dispatch('map:set-marker', lat: (float) $this->lat, lng: (float) $this->lng);
    }

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
                    'key' => config('geocoding.google.key'),
                    'language' => 'uk',
                ]);

            if (! $response->ok()) {
                return null;
            }

            $json = $response->json();

            if (data_get($json, 'status') === 'OK' && is_array(data_get($json, 'results'))) {
                Cache::put($cacheKey, $json, now()->addHours(24));
            }

            return $json;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function scheduleGeocode(): void
    {
        $token = uniqid('geo_', true);
        $this->geocodeToken = $token;
        $this->dispatch('geocode:schedule', token: $token);
    }

    #[On('geocode:debounced')]
    public function runDebouncedGeocode(string $token): void
    {
        if ($this->geocodeToken !== $token) {
            return;
        }

        $this->geocodeFromFields();
    }

    protected function reverseGeocodeFromPoint(float $lat, float $lng): void
    {
        $cacheKey = 'reverse_geocode:' . md5($lat . ',' . $lng);

        $json = $this->googleGeocodeCached(['latlng' => "{$lat},{$lng}"], $cacheKey);

        $streetName = null;
        $houseNumber = null;
        $cityName = null;

        $components = data_get($json, 'results.0.address_components');
        if (is_array($components)) {
            $street = collect($components)->first(fn ($c) => in_array('route', $c['types'] ?? [], true));
            $house = collect($components)->first(fn ($c) => in_array('street_number', $c['types'] ?? [], true));
            $city = collect($components)->first(fn ($c) => in_array('locality', $c['types'] ?? [], true));

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
            }
        }

        $this->suppressAddressHooks = true;

        try {
            $this->street = $streetName ?? $this->street;
            $this->house = $houseNumber ?? $this->house;
            $this->city = $cityName ?? $this->city;
            $this->syncAddressText();
        } finally {
            $this->suppressAddressHooks = false;
        }
    }

    protected function geocodeFromFields(): void
    {
        if (! filled($this->street) || ! filled($this->house)) {
            return;
        }

        if (AddressPrecision::fromNullable($this->address_precision)->isExact()) {
            return;
        }

        $city = filled($this->city) ? $this->city : 'Kyiv';
        $cacheKeySource = "{$city}|{$this->street}|{$this->house}";
        $addressQuery = str_replace('|', ', ', $cacheKeySource);

        $json = $this->googleGeocodeCached(['address' => $addressQuery], 'geocode:' . md5($cacheKeySource));
        $location = data_get($json, 'results.0.geometry.location');

        if (! is_array($location) || ! isset($location['lat'], $location['lng'])) {
            return;
        }

        $this->lat = (float) $location['lat'];
        $this->lng = (float) $location['lng'];
        $this->address_precision = AddressCoordinatePolicy::precisionForFieldGeocode($this->lat, $this->lng)->value;

        $this->pushMarkerToMap();
    }

    protected function hydrateAddressFromCoords(float $lat, float $lng): void
    {
        try {
            $point = app(Geocoder::class)->reverse($lat, $lng);

            if (! $point) {
                return;
            }

            $this->suppressAddressHooks = true;

            if (! empty($point->address)) {
                $this->address_text = $point->address;
            }

            foreach ($point->components ?? [] as $c) {
                $types = $c['types'] ?? [];
                $name = $c['long_name'] ?? $c['name'] ?? null;

                if (! $name) {
                    continue;
                }

                if (in_array('route', $types, true)) {
                    $this->street ??= $name;
                }

                if (in_array('street_number', $types, true) || in_array('house_number', $types, true)) {
                    $this->house ??= $name;
                }

                if (in_array('locality', $types, true) || in_array('city', $types, true)) {
                    $this->city ??= $name;
                }
            }

            if ((! $this->street || ! $this->house) && $this->address_text) {
                if (preg_match('/^(.*?)[,\s]+(\d+[A-Za-zА-Яа-яІЇЄієї\-\/]*)/u', $this->address_text, $m)) {
                    $this->street ??= trim($m[1]);
                    $this->house ??= trim($m[2]);
                }
            }

            $this->address_precision = AddressCoordinatePolicy::precisionForFieldGeocode($this->lat, $this->lng)->value;
        } catch (\Throwable) {
        } finally {
            $this->suppressAddressHooks = false;
        }
    }
}
