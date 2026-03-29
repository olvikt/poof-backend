<?php

namespace App\Support\Courier;

use App\Models\Order;
use App\Models\User;

class CourierNavigationRuntime
{
    public const MAX_CITY_NAVIGATION_DISTANCE_KM = 80;

    public function resolveMapBootstrap(User $courier, ?Order $order): array
    {
        if (! $order || ! $this->validCoords($order->lat, $order->lng)) {
            return [
                'orderLat' => null,
                'orderLng' => null,
                'courierLat' => null,
                'courierLng' => null,
                'courierConfirmed' => false,
            ];
        }

        $hasCourier = $this->validCoords($courier->last_lat, $courier->last_lng);

        return [
            'orderLat' => (float) $order->lat,
            'orderLng' => (float) $order->lng,
            'courierLat' => $hasCourier ? (float) $courier->last_lat : null,
            'courierLng' => $hasCourier ? (float) $courier->last_lng : null,
            'courierConfirmed' => $this->isCourierLocationConfirmedForOrder($courier, $order),
        ];
    }

    public function isCourierLocationConfirmedForOrder(User $courier, Order $order): bool
    {
        if (! $this->validCoords($courier->last_lat, $courier->last_lng)) {
            return false;
        }

        if (! $this->validCoords($order->lat, $order->lng)) {
            return false;
        }

        $distanceKm = $this->haversine(
            (float) $courier->last_lat,
            (float) $courier->last_lng,
            (float) $order->lat,
            (float) $order->lng,
        );

        return $distanceKm <= self::MAX_CITY_NAVIGATION_DISTANCE_KM;
    }

    public function validCoords($lat, $lng): bool
    {
        if ($lat === null || $lng === null) {
            return false;
        }

        if ($lat == 0 && $lng == 0) {
            return false;
        }

        if ($lat < -90 || $lat > 90) {
            return false;
        }

        if ($lng < -180 || $lng > 180) {
            return false;
        }

        return true;
    }

    public function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($dLon / 2)
            * sin($dLon / 2);

        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
