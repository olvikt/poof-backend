<?php

namespace App\Livewire\Courier;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Courier\CourierPresenceService;
use App\Support\Courier\CourierNavigationRuntime;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class AvailableOrders extends Component
{
    private const UI_OPTIMISTIC_SYNC_TTL_SECONDS = 3;
    private const POLL_FAST_SECONDS = 6;
    private const POLL_SLOW_SECONDS = 20;

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
            $this->online = $this->presenceService()->canonicalOnline($user);
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

        $this->online = $this->presenceService()->canonicalOnline($user);
        $this->lastUiOnlineSyncAt = null;
    }

    protected function resolveActiveOrderIfPresent(?User $courier, array $runtime): ?Order
    {
        if (! $courier instanceof User) {
            return null;
        }

        if (! ($runtime['has_active_order'] ?? false)) {
            return null;
        }

        return $this->presenceService()->resolveActiveOrder($courier);
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

        $runtime = $this->presenceService()->snapshot($courier) ?? [];
        $this->repairOnlineStateFromCanonicalSource($courier, $runtime);
        $this->activeOrder = $this->resolveActiveOrderIfPresent($courier, $runtime);

        $orders = OrderOffer::query()
            ->alivePendingForCourierOrders((int) $courier->id)
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
            'pollIntervalSeconds' => $this->availableOrdersPollIntervalSeconds(),
        ])->layout('layouts.courier');
    }

    private function availableOrdersPollIntervalSeconds(): int
    {
        if (! $this->online || $this->activeOrder) {
            return self::POLL_SLOW_SECONDS;
        }

        return self::POLL_FAST_SECONDS;
    }

    private function resolveCourier(): ?User
    {
        return $this->presenceService()->resolveAuthenticatedCourier();
    }

    private function repairOnlineStateFromCanonicalSource(User $courier, array $runtime): void
    {
        $canonicalOnline = (bool) ($runtime['online'] ?? false);

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

    private function presenceService(): CourierPresenceService
    {
        return app(CourierPresenceService::class);
    }

    private function navigationRuntime(): CourierNavigationRuntime
    {
        return app(CourierNavigationRuntime::class);
    }
}
