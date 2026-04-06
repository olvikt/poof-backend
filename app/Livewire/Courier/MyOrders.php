<?php

namespace App\Livewire\Courier;

use App\Models\Order;
use App\Models\User;
use App\Services\Courier\CourierPresenceService;
use App\Services\Dispatch\DispatchTriggerPolicy;
use App\Services\Dispatch\DispatchTriggerService;
use App\Support\Courier\CourierNavigationRuntime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class MyOrders extends Component
{
    private const POLL_ACTIVE_SECONDS = 6;
    private const POLL_IDLE_SECONDS = 20;

    public bool $online = false;

    protected $listeners = [
        'order-updated' => '$refresh',
        'courier-online-toggled' => 'syncOnlineState',
    ];

    public function mount(): void
    {
        $courier = $this->resolveCourier();

        if ($courier instanceof User && $courier->isCourier()) {
            $this->online = $this->presenceService()->canonicalOnline($courier);
        }
    }

    public function syncOnlineState(): void
    {
        $courier = $this->resolveCourier();
        $this->online = $this->presenceService()->canonicalOnline($courier);
    }

    public function start(int $orderId): void
    {
        $courier = $this->resolveCourier();

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

    public function complete(int $orderId): void
    {
        $courier = $this->resolveCourier();

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

        app(DispatchTriggerService::class)->triggerQueueBatch(
            DispatchTriggerPolicy::SOURCE_ORDER_COMPLETED,
            (int) config('dispatch.radius_km', 20),
            ['courier_id' => $courier->id],
        );

        $this->dispatch('notify', type: 'success', message: 'Замовлення виконано');
        $this->dispatch('$refresh');
    }

    public function navigate(int $orderId): void
    {
        $courier = $this->resolveCourier();

        if (! $courier instanceof User || ! $courier->isCourier()) {
            return;
        }

        $order = Order::whereKey($orderId)
            ->where('courier_id', $courier->id)
            ->first();

        if (! $order) {
            return;
        }

        $runtime = $this->navigationRuntime();

        if (! $runtime->validCoords($courier->last_lat, $courier->last_lng)) {
            $this->dispatch('notify', type: 'error', message: 'Локація курʼєра недоступна');

            return;
        }

        if (! $runtime->validCoords($order->lat, $order->lng)) {
            $this->dispatch('notify', type: 'error', message: 'Локація замовлення недоступна');

            return;
        }

        if (! $runtime->isCourierLocationConfirmedForOrder($courier, $order)) {
            $this->dispatch('notify', type: 'error', message: 'Локація курʼєра не підтверджена');
            $this->dispatch('map:ui-error', [
                'message' => 'Локація курʼєра не підтверджена',
            ]);

            return;
        }

        $this->dispatch('map:courier-update', [
            'courierLat' => (float) $courier->last_lat,
            'courierLng' => (float) $courier->last_lng,
            'orderLat' => (float) $order->lat,
            'orderLng' => (float) $order->lng,
        ]);

        $this->dispatch('build-route', [
            'fromLat' => (float) $courier->last_lat,
            'fromLng' => (float) $courier->last_lng,
            'toLat' => (float) $order->lat,
            'toLng' => (float) $order->lng,
        ]);
    }

    public function render()
    {
        $startedAt = microtime(true);
        $courier = $this->resolveCourier();

        if (! $courier instanceof User || ! $courier->isCourier()) {
            return view('livewire.courier.my-orders', [
                'orders' => collect(),
                'online' => false,
            ])->layout('layouts.courier');
        }

        $this->online = $this->presenceService()->canonicalOnline($courier);

        $orders = Order::where('courier_id', $courier->id)
            ->whereIn('status', [
                Order::STATUS_ACCEPTED,
                Order::STATUS_IN_PROGRESS,
            ])
            ->with(['client:id,phone'])
            ->orderBy('accepted_at')
            ->get();

        $orders = $this->appendDistance($orders, $courier);

        Log::debug('my_orders_render', [
            'flow' => 'courier_cabinet',
            'courier_id' => $courier->id,
            'active_order_count' => $orders->count(),
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return view('livewire.courier.my-orders', [
            'orders' => $orders,
            'online' => $this->online,
            'mapBootstrap' => $this->resolveMapBootstrap($orders, $courier),
            'pollIntervalSeconds' => $orders->isEmpty() ? self::POLL_IDLE_SECONDS : self::POLL_ACTIVE_SECONDS,
        ])->layout('layouts.courier');
    }

    private function resolveMapBootstrap(Collection $orders, User $courier): array
    {
        $runtime = $this->navigationRuntime();

        $activeOrder = $orders
            ->first(fn ($order) => $runtime->validCoords($order->lat, $order->lng));

        $payload = $runtime->resolveMapBootstrap($courier, $activeOrder);

        if (config('dispatch.courier_map_bootstrap_debug') && $activeOrder) {
            Log::debug('courier map bootstrap prepared', [
                'courier_id' => $courier->id,
                'order_id' => $activeOrder->id,
                'has_courier_coordinates' => $payload['courierLat'] !== null && $payload['courierLng'] !== null,
                'courier_confirmed' => $payload['courierConfirmed'],
            ]);
        }

        return $payload;
    }

    private function appendDistance(Collection $orders, User $courier): Collection
    {
        $runtime = $this->navigationRuntime();

        return $orders->map(function ($order) use ($courier, $runtime) {
            if (! $runtime->isCourierLocationConfirmedForOrder($courier, $order)) {
                $order->distance_km = null;
                $order->eta_minutes = null;

                return $order;
            }

            $order->distance_km = round(
                $runtime->haversine(
                    (float) $courier->last_lat,
                    (float) $courier->last_lng,
                    (float) $order->lat,
                    (float) $order->lng,
                ),
                2,
            );

            return $order;
        });
    }

    private function resolveCourier(): ?User
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isCourier()) {
            return null;
        }

        return $user->fresh(['courierProfile']);
    }

    private function presenceService(): CourierPresenceService
    {
        return app(CourierPresenceService::class);
    }

    private function navigationRuntime(): CourierNavigationRuntime
    {
        return app(CourierNavigationRuntime::class);
    }
}
