<?php

namespace App\Livewire\Courier;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Support\Courier\CourierNavigationRuntime;
use Livewire\Component;

class AvailableOrders extends Component
{
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
        'order-updated' => '$refresh',
    ];

    public function mount(): void
    {
        $user = $this->resolveCourier();

        if ($user instanceof User && $user->isCourier()) {
            $runtime = $user->courierRuntimeSnapshot();
            $this->online = (bool) ($runtime['online'] ?? false);
            $this->lastUiOnlineSyncAt = null;
        }
    }

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

    public function render()
    {
        $courier = $this->resolveCourier();

        if (! $courier instanceof User || ! $courier->isCourier()) {
            $this->activeOrder = null;

            return view('livewire.courier.available-orders', [
                'orders' => collect(),
                'geoRequired' => false,
                'online' => false,
                'activeOrder' => null,
            ])->layout('layouts.courier');
        }

        $this->repairOnlineStateFromCanonicalSource($courier);
        $this->activeOrder = $this->resolveActiveOrder($courier);

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
            'orders' => $orders,
            'geoRequired' => false,
            'online' => $this->online,
            'activeOrder' => $this->activeOrder,
            'mapBootstrap' => $this->navigationRuntime()->resolveMapBootstrap($courier, $this->activeOrder),
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

    private function navigationRuntime(): CourierNavigationRuntime
    {
        return app(CourierNavigationRuntime::class);
    }
}
