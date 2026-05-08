<?php

namespace App\Livewire\Courier;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Courier\CourierPresenceService;
use App\Support\Courier\CourierNavigationRuntime;
use App\Support\Courier\Observability\CourierRuntimeRequestCollector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

        $this->collector()->observeEndpoint('available_orders_render', 'livewire', $startedAt, [
            'has_active_order' => $this->activeOrder !== null,
            'online' => $this->online,
            'status' => (string) ($runtime['status'] ?? null),
        ]);

        return view('livewire.courier.available-orders', [
            'orders' => $orders,
            'geoRequired' => false,
            'online' => $this->online,
            'activeOrder' => $this->activeOrder,
            'emptyState' => $this->resolveEmptyState($courier, $runtime, $orders),
            'mapBootstrap' => $this->navigationRuntime()->resolveMapBootstrap($courier, $this->activeOrder),
            'pollIntervalSeconds' => $this->availableOrdersPollIntervalSeconds(),
        ])->layout('layouts.courier');
    }

    private function resolveEmptyState(User $courier, array $runtime, Collection $orders): array
    {
        if ($this->activeOrder !== null) {
            return [
                'is_offline' => ! $this->online,
                'location_stale' => false,
                'has_pending_offer' => false,
                'nearby_soon_count' => 0,
                'nearby_soon_nearest_at' => null,
                'nearby_searching_now_count' => 0,
                'show_neutral_searching_hint' => false,
            ];
        }

        if ($orders->isNotEmpty()) {
            return [
                'is_offline' => ! $this->online,
                'location_stale' => false,
                'has_pending_offer' => true,
                'nearby_soon_count' => 0,
                'nearby_soon_nearest_at' => null,
                'nearby_searching_now_count' => 0,
                'show_neutral_searching_hint' => false,
            ];
        }

        $locationStale = $this->isLocationStale($courier);
        $nearbySoon = $this->nearbySoonJobs($courier);
        $nearbySearchingNow = $this->nearbySearchingCount($courier);

        return [
            'is_offline' => ! $this->online,
            'location_stale' => $locationStale,
            'has_pending_offer' => $orders->isNotEmpty(),
            'nearby_soon_count' => $nearbySoon['count'],
            'nearby_soon_nearest_at' => $nearbySoon['nearest_at'],
            'nearby_searching_now_count' => $nearbySearchingNow,
            'show_neutral_searching_hint' => $this->online && $orders->isEmpty() && $nearbySearchingNow > 0,
        ];
    }

    private function isLocationStale(User $courier): bool
    {
        $lastLocationAt = $courier->courierProfile?->last_location_at;
        $staleSeconds = max(30, (int) config('courier_runtime.freshness.dispatch_candidate_location_seconds', 60));

        return ! $lastLocationAt instanceof Carbon || $lastLocationAt->lte(now()->subSeconds($staleSeconds));
    }

    private function nearbySoonJobs(User $courier): array
    {
        if (! is_numeric($courier->last_lat) || ! is_numeric($courier->last_lng)) {
            return ['count' => 0, 'nearest_at' => null];
        }

        [$latMin, $latMax, $lngMin, $lngMax] = $this->distanceBoundingBox((float) $courier->last_lat, (float) $courier->last_lng, (float) config('dispatch.search_radius_km', 5));

        $rows = Order::query()
            ->where('status', Order::STATUS_SEARCHING)
            ->where('payment_status', Order::PAY_PAID)
            ->whereNull('courier_id')
            ->whereDate('scheduled_date', now()->toDateString())
            ->whereNotNull('dispatch_available_at')
            ->where('dispatch_available_at', '>', now())
            ->whereBetween('lat', [$latMin, $latMax])
            ->whereBetween('lng', [$lngMin, $lngMax])
            ->get(['dispatch_available_at']);

        return [
            'count' => $rows->count(),
            'nearest_at' => $rows->min('dispatch_available_at'),
        ];
    }

    private function nearbySearchingCount(User $courier): int
    {
        if (! is_numeric($courier->last_lat) || ! is_numeric($courier->last_lng)) {
            return 0;
        }

        [$latMin, $latMax, $lngMin, $lngMax] = $this->distanceBoundingBox((float) $courier->last_lat, (float) $courier->last_lng, (float) config('dispatch.search_radius_km', 5));

        return Order::query()
            ->where('status', Order::STATUS_SEARCHING)
            ->where('payment_status', Order::PAY_PAID)
            ->whereNull('courier_id')
            ->where(function ($q): void {
                $q->whereNull('dispatch_available_at')
                    ->orWhere('dispatch_available_at', '<=', now());
            })
            ->whereBetween('lat', [$latMin, $latMax])
            ->whereBetween('lng', [$lngMin, $lngMax])
            ->count();
    }

    private function distanceBoundingBox(float $lat, float $lng, float $radiusKm): array
    {
        $latDelta = $radiusKm / 111.0;
        $lngDivisor = max(cos(deg2rad($lat)), 0.01);
        $lngDelta = $radiusKm / (111.0 * $lngDivisor);

        return [$lat - $latDelta, $lat + $latDelta, $lng - $lngDelta, $lng + $lngDelta];
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

    private function collector(): CourierRuntimeRequestCollector
    {
        return app(CourierRuntimeRequestCollector::class);
    }
}
