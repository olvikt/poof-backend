<?php

namespace App\Livewire\Courier;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Livewire\Component;

class AvailableOrders extends Component
{
    private const MAX_CITY_NAVIGATION_DISTANCE_KM = 80;
    private const UI_OPTIMISTIC_SYNC_TTL_SECONDS = 3;

    public bool $online = false;
    public ?int $lastUiOnlineSyncAt = null;

    /**
     * Активное замовлення кур'єра (accepted / in_progress).
     * Заповнюється на render(), щоб завжди бути актуальним.
     */
    public ?Order $activeOrder = null;

    protected $listeners = [
        'courier-online-toggled' => 'syncOnlineState',
        'order-updated'          => '$refresh',
    ];

    /* -------------------------------------------------
     | MOUNT
     | ------------------------------------------------- */
    public function mount(): void
    {
        $user = $this->resolveCourier();

        if ($user instanceof User && $user->isCourier()) {
            $runtime = $user->courierRuntimeSnapshot();
            $this->online = (bool) ($runtime['online'] ?? false);
            $this->lastUiOnlineSyncAt = null;
        }
    }

    /* -------------------------------------------------
     | SYNC ONLINE STATE (reactive)
     | ------------------------------------------------- */
    public function syncOnlineState(?bool $online = null): void
    {
        if (is_bool($online)) {
            $this->online = $online;
            $this->lastUiOnlineSyncAt = now()->timestamp;

            return;
        }

        $user = $this->resolveCourier();

        if ($user instanceof User && $user->isCourier()) {
            $runtime = $user->courierRuntimeSnapshot();
            $this->online = (bool) ($runtime['online'] ?? false);
            $this->lastUiOnlineSyncAt = null;
        }
    }

    /* -------------------------------------------------
     | ACTIVE ORDER (accepted / in_progress)
     | ------------------------------------------------- */
    protected function resolveActiveOrder(?User $courier): ?Order
    {
        if (! $courier instanceof User) {
            return null;
        }

        return Order::query()
            ->where('courier_id', $courier->id)
            ->whereIn('status', [
                Order::STATUS_ACCEPTED,
                Order::STATUS_IN_PROGRESS,
            ])
            ->latest('accepted_at')
            ->first();
    }

    /* -------------------------------------------------
     | RENDER
     | ------------------------------------------------- */
    public function render()
    {
        $courier = $this->resolveCourier();

        if (! $courier instanceof User || ! $courier->isCourier()) {
            $this->activeOrder = null;

            return view('livewire.courier.available-orders', [
                'orders'       => collect(),
                'geoRequired'  => false,
                'online'       => false,
                'activeOrder'  => null,
            ])->layout('layouts.courier');
        }

        // Conservative self-heal:
        // keep short optimistic UI window after toggle event, but then force
        // polling/render back to canonical backend runtime source.
        $this->repairOnlineStateFromCanonicalSource($courier);

        // 1) Активне замовлення (якщо є) — використаємо для UI-блоків знизу
        $this->activeOrder = $this->resolveActiveOrder($courier);

        // 2) Доступні оффери (pending) — показуємо як “вхідні” замовлення
        //    Якщо є активне замовлення — можемо все одно витягнути список,
        //    але UI нижче підкаже, що нові брати не можна.
        $orders = OrderOffer::query()
            ->where('courier_id', $courier->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->with('order')
            ->latest()
            ->get()
            ->pluck('order')
            ->filter();

        return view('livewire.courier.available-orders', [
            'orders'       => $orders,
            'geoRequired'  => false,
            'online'       => $this->online,
            'activeOrder'  => $this->activeOrder,
            'mapBootstrap' => $this->resolveMapBootstrap($courier, $this->activeOrder),
        ])->layout('layouts.courier');
    }

    private function resolveCourier(): ?User
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isCourier()) {
            return null;
        }

        return $user->fresh(['courierProfile']);
    }

    private function resolveMapBootstrap(User $courier, ?Order $activeOrder): array
    {
        if (! $activeOrder || ! $this->validCoords($activeOrder->lat, $activeOrder->lng)) {
            return [
                'orderLat' => null,
                'orderLng' => null,
                'courierLat' => null,
                'courierLng' => null,
                'courierConfirmed' => false,
            ];
        }

        $hasCourier = $this->validCoords($courier->last_lat, $courier->last_lng);
        $courierConfirmed = false;

        if ($hasCourier) {
            $distanceKm = $this->haversine(
                (float) $courier->last_lat,
                (float) $courier->last_lng,
                (float) $activeOrder->lat,
                (float) $activeOrder->lng,
            );

            $courierConfirmed = $distanceKm <= self::MAX_CITY_NAVIGATION_DISTANCE_KM;
        }

        return [
            'orderLat' => (float) $activeOrder->lat,
            'orderLng' => (float) $activeOrder->lng,
            'courierLat' => $hasCourier ? (float) $courier->last_lat : null,
            'courierLng' => $hasCourier ? (float) $courier->last_lng : null,
            'courierConfirmed' => $courierConfirmed,
        ];
    }

    private function repairOnlineStateFromCanonicalSource(User $courier): void
    {
        $runtime = $courier->courierRuntimeSnapshot();
        $canonicalOnline = (bool) ($runtime['online'] ?? false);

        if ($this->lastUiOnlineSyncAt !== null) {
            $optimisticAge = now()->timestamp - $this->lastUiOnlineSyncAt;

            if ($optimisticAge <= self::UI_OPTIMISTIC_SYNC_TTL_SECONDS) {
                return;
            }
        }

        $this->online = $canonicalOnline;
        $this->lastUiOnlineSyncAt = null;
    }

    private function validCoords($lat, $lng): bool
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

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
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
