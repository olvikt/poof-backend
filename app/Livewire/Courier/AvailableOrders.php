<?php

namespace App\Livewire\Courier;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Support\Courier\CourierNavigationRuntime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
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

    public function syncOnlineState(?bool $online = null, ?bool $changed = null): void
    {
        if (is_bool($online) && $changed === true) {
            $this->online = $online;
            $this->lastUiOnlineSyncAt = now()->timestamp;

            return;
        }

        $user = $this->resolveCourier();

        $this->online = $this->resolveCanonicalOnlineState($user);
        $this->lastUiOnlineSyncAt = null;
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
        $startedAt = microtime(true);
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

        $orders = Order::query()
            ->join('order_offers', 'order_offers.order_id', '=', 'orders.id')
            ->where('order_offers.courier_id', $courier->id)
            ->where('order_offers.status', OrderOffer::STATUS_PENDING)
            ->whereNotNull('order_offers.expires_at')
            ->where('order_offers.expires_at', '>', now())
            ->select('orders.*')
            ->orderByDesc('order_offers.created_at')
            ->distinct()
            ->get();

        Log::debug('available_orders_render', [
            'flow' => 'courier_cabinet',
            'courier_id' => $courier->id,
            'pending_offer_count' => $orders->count(),
            'active_order_count' => $this->activeOrder ? 1 : 0,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

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

        return User::query()
            ->select([
                'id',
                'role',
                'is_active',
                'is_online',
                'is_busy',
                'session_state',
                'last_lat',
                'last_lng',
            ])
            ->with([
                'courierProfile' => fn (Builder $query) => $query->select('id', 'user_id', 'status', 'last_location_at'),
            ])
            ->find($user->id);
    }

    private function repairOnlineStateFromCanonicalSource(User $courier): void
    {
        $canonicalOnline = $this->resolveCanonicalOnlineState($courier);

        if ($this->lastUiOnlineSyncAt !== null) {
            $optimisticAge = now()->timestamp - $this->lastUiOnlineSyncAt;

            if ($optimisticAge <= self::UI_OPTIMISTIC_SYNC_TTL_SECONDS) {
                return;
            }
        }

        if ($this->online !== $canonicalOnline) {
            Log::warning('optimistic_runtime_state_overridden', [
                'flow' => 'courier_online_state',
                'user_id' => $courier->id,
                'optimistic_online' => $this->online,
                'canonical_online' => $canonicalOnline,
                'last_ui_sync_at' => $this->lastUiOnlineSyncAt,
            ]);
        }

        $this->online = $canonicalOnline;
        $this->lastUiOnlineSyncAt = null;
    }

    private function resolveCanonicalOnlineState(?User $courier): bool
    {
        if (! $courier instanceof User || ! $courier->isCourier()) {
            return false;
        }

        $runtime = $courier->courierRuntimeSnapshot();

        return (bool) ($runtime['online'] ?? false);
    }

    private function navigationRuntime(): CourierNavigationRuntime
    {
        return app(CourierNavigationRuntime::class);
    }
}
