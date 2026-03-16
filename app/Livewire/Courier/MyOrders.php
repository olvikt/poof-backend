<?php

namespace App\Livewire\Courier;

use App\Models\Order;
use App\Models\User;
use Livewire\Component;
use App\Services\Dispatch\OfferDispatcher;
use Illuminate\Support\Collection;

class MyOrders extends Component
{
    public bool $online = false;

    protected $listeners = [
        'order-updated' => '$refresh',
        'courier-online-toggled' => 'syncOnlineState',
    ];

    public function mount(): void
    {
        $courier = auth()->user();

        if ($courier instanceof User && $courier->isCourier()) {
            $this->online = $courier->isCourierOnline();
        }
    }

    public function syncOnlineState(?bool $online = null): void
    {
        if (is_bool($online)) {
            $this->online = $online;

            return;
        }

        $courier = auth()->user();

        if ($courier instanceof User && $courier->isCourier()) {
            $this->online = $courier->isCourierOnline();
        }
    }

    /* =========================================================
     | START ORDER
     ========================================================= */

    public function start(int $orderId): void
    {
        $courier = auth()->user();

        if (! $courier instanceof User || ! $courier->isCourier()) {
            return;
        }

        $order = Order::whereKey($orderId)
            ->where('courier_id', $courier->id)
            ->first();

        if (! $order) {
            $this->dispatch('notify', type: 'error', message: 'Замовлення не знайдено');
            return;
        }

        if (! $order->startBy($courier)) {
            $this->dispatch('notify', type: 'error', message: 'Не можна почати це замовлення');
            return;
        }

        $this->dispatch('notify', type: 'success', message: 'Виконання розпочато');
        $this->dispatch('$refresh');
    }

    /* =========================================================
     | COMPLETE ORDER
     ========================================================= */

    public function complete(int $orderId): void
    {
        $courier = auth()->user();

        if (! $courier instanceof User || ! $courier->isCourier()) {
            return;
        }

        $order = Order::whereKey($orderId)
            ->where('courier_id', $courier->id)
            ->first();

        if (! $order) {
            $this->dispatch('notify', type: 'error', message: 'Замовлення не знайдено');
            return;
        }

        if (! $order->completeBy($courier)) {
            $this->dispatch('notify', type: 'error', message: 'Не можна завершити це замовлення');
            return;
        }

        app(OfferDispatcher::class)->dispatchSearchingOrders();

        $this->dispatch('notify', type: 'success', message: 'Замовлення виконано');
        $this->dispatch('$refresh');
    }

    /* =========================================================
     | NAVIGATE
     ========================================================= */

    public function navigate(int $orderId): void
    {
        $courier = auth()->user();

        if (! $courier instanceof User || ! $courier->isCourier()) {
            return;
        }

        $order = Order::whereKey($orderId)
            ->where('courier_id', $courier->id)
            ->first();

        if (! $order) {
            return;
        }

        if (! $this->validCoords($courier->last_lat, $courier->last_lng)) {
            $this->dispatch('notify', type: 'error', message: 'Локація курʼєра недоступна');
            return;
        }

        if (! $this->validCoords($order->lat, $order->lng)) {
            $this->dispatch('notify', type: 'error', message: 'Локація замовлення недоступна');
            return;
        }

        $this->dispatch('map:courier-update', [
            'courierLat' => (float)$courier->last_lat,
            'courierLng' => (float)$courier->last_lng,
            'orderLat'   => (float)$order->lat,
            'orderLng'   => (float)$order->lng,
        ]);

        $this->dispatch('build-route', [
            'fromLat' => (float)$courier->last_lat,
            'fromLng' => (float)$courier->last_lng,
            'toLat'   => (float)$order->lat,
            'toLng'   => (float)$order->lng,
        ]);
    }

    /* =========================================================
     | RENDER
     ========================================================= */

    public function render()
    {
        $courier = auth()->user();

        if (! $courier instanceof User || ! $courier->isCourier()) {
            return view('livewire.courier.my-orders', [
                'orders' => collect(),
                'online' => false,
            ])->layout('layouts.courier');
        }

        $this->online = $courier->isCourierOnline();

        // 🔥 БЕЗ scopeActiveForCourier — максимально безопасно
        $orders = Order::where('courier_id', $courier->id)
            ->whereIn('status', [
                Order::STATUS_ACCEPTED,
                Order::STATUS_IN_PROGRESS,
            ])
            ->orderBy('accepted_at')
            ->get();

        $orders = $this->appendDistance($orders, $courier);

        return view('livewire.courier.my-orders', [
            'orders' => $orders,
            'online' => $this->online,
        ])->layout('layouts.courier');
    }

    /* =========================================================
     | DISTANCE
     ========================================================= */

    private function appendDistance(Collection $orders, User $courier): Collection
    {
        return $orders->map(function ($order) use ($courier) {

            if (! $this->validCoords($courier->last_lat, $courier->last_lng)) {
                $order->distance_km = null;
                return $order;
            }

            if (! $this->validCoords($order->lat, $order->lng)) {
                $order->distance_km = null;
                return $order;
            }

            $order->distance_km = round(
                $this->haversine(
                    (float)$courier->last_lat,
                    (float)$courier->last_lng,
                    (float)$order->lat,
                    (float)$order->lng
                ),
                2
            );

            return $order;
        });
    }

    private function validCoords($lat, $lng): bool
    {
        if ($lat === null || $lng === null) return false;
        if ($lat == 0 && $lng == 0) return false;
        if ($lat < -90 || $lat > 90) return false;
        if ($lng < -180 || $lng > 180) return false;
        return true;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2)
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($dLon/2)
            * sin($dLon/2);

        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
