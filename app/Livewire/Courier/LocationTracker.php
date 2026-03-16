<?php

namespace App\Livewire\Courier;

use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use App\Services\Dispatch\OfferDispatcher;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class LocationTracker extends Component
{
    /**
     * JS → Livewire listener
     */
    protected $listeners = [
        'courier-location' => 'updateLocation',
    ];

    public function booted(): void
    {
        $this->dispatch('courier:tracker-ready');
    }

    /**
     * 📍 Получение координат от фронта
     * ⚠ Без type-hint'ов (требование Livewire listeners)
     */
    public function mount(): void
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isCourier()) {
            return;
        }

        $courierProfile = $user->courierProfile;

        if (! $courierProfile) {
            return;
        }

        if (
            in_array($courierProfile->status, Courier::ACTIVE_MAP_STATUSES, true) &&
            $user->last_lat &&
            $user->last_lng
        ) {
            $this->dispatch('map:courier-update', [
                'courier' => [
                    'lat' => $user->last_lat,
                    'lng' => $user->last_lng,
                ],
            ]);
        }
    }

    public function updateLocation($lat, $lng, $accuracy = null): void
    {
        $user = auth()->user();

        // 🔒 Только авторизованный курьер
        if (! $user instanceof User || ! $user->isCourier()) {
            return;
        }

        $courierProfile = $user->courierProfile;

        if (! $courierProfile) {
            return;
        }

        // Приведение типов
        $lat = (float) $lat;
        $lng = (float) $lng;
        $accuracy = $accuracy !== null ? (float) $accuracy : null;

        // ❌ защита от мусора
        if (
            $lat < -90 || $lat > 90 ||
            $lng < -180 || $lng > 180
        ) {
            return;
        }

        // ❌ фильтр неточного GPS
        if ($accuracy && $accuracy > 100) {
            return;
        }

        $distanceMoved = null;

        if ($user->last_lat !== null && $user->last_lng !== null) {
            $distanceMoved = $this->distanceMeters(
                (float) $user->last_lat,
                (float) $user->last_lng,
                $lat,
                $lng
            );
        }

        $hasMovedEnough = $distanceMoved === null || $distanceMoved > 50;
        $hasSearchingOrders = false;
        $dispatchTime = null;

        if (
            (bool) $user->is_online &&
            $courierProfile->status === Courier::STATUS_OFFLINE
        ) {
            $courierProfile->status = Courier::STATUS_ONLINE;
        }

        if (
            $courierProfile->status === Courier::STATUS_ONLINE &&
            $hasMovedEnough
        ) {
            $hasSearchingOrders = Order::query()
                ->where('status', Order::STATUS_SEARCHING)
                ->exists();

            if (
                $hasSearchingOrders &&
                (
                    ! $user->last_dispatch_at ||
                    $user->last_dispatch_at->diffInSeconds(now()) >= 5
                )
            ) {
                app(OfferDispatcher::class)->dispatchSearchingOrders(
                    (int) config('dispatch.radius_km', 20)
                );

                $dispatchTime = now();

                Log::channel('dispatch')->debug('dispatch triggered', [
                    'courier_id' => $user->id,
                    'distance_moved' => $distanceMoved,
                    'orders_searching' => $hasSearchingOrders,
                ]);
            }
        }

        // -------------------------------------------------
        // Обновляем координаты
        // -------------------------------------------------

        $user->update([
            'last_lat' => $lat,
            'last_lng' => $lng,
            'is_online' => true,
            'last_seen_at' => now(),
            'last_dispatch_at' => $dispatchTime ?? $user->last_dispatch_at,
        ]);

        $courierProfile->update([
            'status' => $courierProfile->status,
            'last_location_at' => now(),
        ]);

        // -------------------------------------------------
        // Обновляем карту (JS)
        // -------------------------------------------------

        $this->dispatch('map:courier-update', [
            'courier' => [
                'lat' => $lat,
                'lng' => $lng,
            ],
        ]);
    }

    protected function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;

        $latDiff = deg2rad($lat2 - $lat1);
        $lngDiff = deg2rad($lng2 - $lng1);

        $a = sin($latDiff / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDiff / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }

    /**
     * Headless component
     */
    public function render()
    {
        return view('livewire.courier.location-tracker');
    }
}
