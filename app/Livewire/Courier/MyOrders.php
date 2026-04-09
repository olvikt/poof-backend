<?php

namespace App\Livewire\Courier;

use App\Actions\Orders\Completion\UploadOrderCompletionProofAction;
use App\Models\Order;
use App\Models\OrderCompletionProof;
use App\Models\OrderCompletionRequest;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Courier\CourierPresenceService;
use App\Services\Courier\Earnings\CourierCompletedOrdersDailyStatsQuery;
use App\Services\Courier\Earnings\OrderCourierNetEarningPreviewService;
use App\Services\Dispatch\DispatchTriggerPolicy;
use App\Services\Dispatch\DispatchTriggerService;
use App\Support\Courier\CourierNavigationRuntime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class MyOrders extends Component
{
    use WithFileUploads;

    private const POLL_ACTIVE_SECONDS = 6;
    private const POLL_IDLE_SECONDS = 20;

    public bool $online = false;
    public array $doorProofFiles = [];
    public array $containerProofFiles = [];
    public ?int $completionConfirmationOrderId = null;
    public array $expandedCompletedStatDates = [];
    public bool $hasInitializedCompletedStatsExpansion = false;
    public bool $hasUserInteractedWithCompletedStats = false;
    public string $activeTab = 'orders';
    public bool $statsPaneUnavailable = false;

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
        $order->refresh();
        if ($this->isProofAware($order)) {
            $this->dispatch('courier-proof:reveal', orderId: $order->id);
        }
        $this->dispatch('$refresh');
    }

    public function toggleCompletedStatDate(string $date): void
    {
        $this->hasUserInteractedWithCompletedStats = true;

        if (in_array($date, $this->expandedCompletedStatDates, true)) {
            $this->expandedCompletedStatDates = array_values(array_filter(
                $this->expandedCompletedStatDates,
                fn (string $item): bool => $item !== $date,
            ));

            return;
        }

        $this->expandedCompletedStatDates[] = $date;
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['orders', 'stats'], true)) {
            return;
        }

        $this->activeTab = $tab;
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
            if ($this->isProofAware($order) && ! $this->hasAllProofs($order)) {
                $this->dispatch('notify', type: 'error', message: 'Додайте 2 фото (біля дверей і контейнера), щоб відправити завершення.');
                return;
            }

            $this->dispatch('notify', type: 'error', message: 'Не можна завершити це замовлення');

            return;
        }

        app(DispatchTriggerService::class)->triggerQueueBatch(
            DispatchTriggerPolicy::SOURCE_ORDER_COMPLETED,
            (int) config('dispatch.radius_km', 20),
            ['courier_id' => $courier->id],
        );

        $this->dispatch('notify', type: 'success', message: $this->isProofAware($order)
            ? 'Підтвердження відправлено клієнту'
            : 'Замовлення виконано');
        $this->dispatch('$refresh');
    }

    public function uploadProof(
        int $orderId,
        string $proofType,
        string $capturedVia = 'file_fallback',
        ?string $clientDeviceClockAt = null,
    ): void
    {
        $courier = $this->resolveCourier();

        if (! $courier instanceof User || ! $courier->isCourier()) {
            return;
        }

        $order = Order::query()
            ->whereKey($orderId)
            ->where('courier_id', $courier->id)
            ->first();

        if (! $order || ! $this->isProofAware($order)) {
            $this->dispatch('notify', type: 'error', message: 'Для цього замовлення фото-підтвердження не потрібне.');
            return;
        }

        $file = $proofType === OrderCompletionProof::TYPE_DOOR_PHOTO
            ? ($this->doorProofFiles[$orderId] ?? null)
            : ($this->containerProofFiles[$orderId] ?? null);

        if (! $file) {
            $this->dispatch('notify', type: 'error', message: 'Оберіть фото перед завантаженням.');
            return;
        }

        $path = $file->store('completion-proofs/'.$orderId, 'public');

        $uploaded = app(UploadOrderCompletionProofAction::class)->handle(
            order: $order,
            courier: $courier,
            proofType: $proofType,
            filePath: $path,
            fileDisk: 'public',
            mimeType: $file->getMimeType(),
            fileSizeBytes: $file->getSize(),
            capturedVia: $capturedVia,
            clientDeviceClockAt: $clientDeviceClockAt,
        );

        if (! $uploaded) {
            $this->dispatch('notify', type: 'error', message: 'Не вдалося завантажити фото-підтвердження.');
            return;
        }

        if ($proofType === OrderCompletionProof::TYPE_DOOR_PHOTO) {
            unset($this->doorProofFiles[$orderId]);
        } else {
            unset($this->containerProofFiles[$orderId]);
        }

        $this->dispatch('notify', type: 'success', message: 'Фото-підтвердження збережено.');
        $this->dispatch('$refresh');
    }

    public function requestCompletionConfirmation(int $orderId): void
    {
        $courier = $this->resolveCourier();

        if (! $courier instanceof User || ! $courier->isCourier()) {
            return;
        }

        $order = Order::query()
            ->whereKey($orderId)
            ->where('courier_id', $courier->id)
            ->first();

        if (! $order) {
            $this->dispatch('notify', type: 'error', message: 'Замовлення не знайдено');

            return;
        }

        if (! $this->isProofAware($order)) {
            $this->complete($orderId);

            return;
        }

        if (! $this->hasAllProofs($order)) {
            $this->dispatch('notify', type: 'error', message: 'Додайте 2 фото (біля дверей і контейнера), щоб відправити завершення.');

            return;
        }

        $this->completionConfirmationOrderId = $order->id;
    }

    public function closeCompletionConfirmation(): void
    {
        $this->completionConfirmationOrderId = null;
    }

    public function confirmCompletion(): void
    {
        if (! $this->completionConfirmationOrderId) {
            return;
        }

        $orderId = $this->completionConfirmationOrderId;
        $this->completionConfirmationOrderId = null;

        $this->complete($orderId);
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
                'completedStats' => collect(),
                'orderEarningPreviews' => [],
                'nearbyAreaSummary' => $this->defaultNearbyAreaSummary(),
                'statsPaneUnavailable' => false,
            ])->layout('layouts.courier');
        }

        $this->online = $this->presenceService()->canonicalOnline($courier);

        $runtimeStartedAt = microtime(true);
        $orders = Order::where('courier_id', $courier->id)
            ->whereIn('status', [
                Order::STATUS_ACCEPTED,
                Order::STATUS_IN_PROGRESS,
            ])
            ->with(['client:id,phone', 'completionRequest.proofs'])
            ->orderBy('accepted_at')
            ->get();

        $orders = $this->appendDistance($orders, $courier);
        $runtimeElapsedMs = (int) round((microtime(true) - $runtimeStartedAt) * 1000);
        $nearbyAreaSummary = $this->resolveNearbyAreaSummaryCached($courier);

        Log::debug('my_orders_runtime_render', [
            'flow' => 'courier_cabinet',
            'courier_id' => $courier->id,
            'active_order_count' => $orders->count(),
            'elapsed_ms' => $runtimeElapsedMs,
        ]);

        $completedStats = collect();
        $completedStatsCount = 0;
        $statsElapsedMs = 0;
        $this->statsPaneUnavailable = false;

        if ($this->activeTab === 'stats') {
            $statsStartedAt = microtime(true);
            $completedStats = $this->resolveCompletedStats($courier);
            $completedStatsCount = $completedStats->count();
            $statsElapsedMs = (int) round((microtime(true) - $statsStartedAt) * 1000);

            Log::debug('my_orders_stats_render', [
                'flow' => 'courier_cabinet',
                'courier_id' => $courier->id,
                'completed_stats_count' => $completedStatsCount,
                'active_order_count' => $orders->count(),
                'elapsed_ms' => $statsElapsedMs,
                'stats_pane_unavailable' => $this->statsPaneUnavailable,
            ]);
        }

        Log::debug('my_orders_render', [
            'flow' => 'courier_cabinet',
            'courier_id' => $courier->id,
            'active_order_count' => $orders->count(),
            'completed_stats_count' => $completedStatsCount,
            'runtime_elapsed_ms' => $runtimeElapsedMs,
            'stats_elapsed_ms' => $statsElapsedMs,
            'active_tab' => $this->activeTab,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return view('livewire.courier.my-orders', [
            'orders' => $orders,
            'online' => $this->online,
            'completedStats' => $completedStats,
            'orderEarningPreviews' => $orders->mapWithKeys(
                fn (Order $order): array => [$order->id => $this->earningPreviewService()->forOrder($order)]
            )->all(),
            'nearbyAreaSummary' => $nearbyAreaSummary,
            'statsPaneUnavailable' => $this->statsPaneUnavailable,
            'mapBootstrap' => $this->resolveMapBootstrap($orders, $courier),
            'pollIntervalSeconds' => $orders->isEmpty() ? self::POLL_IDLE_SECONDS : self::POLL_ACTIVE_SECONDS,
        ])->layout('layouts.courier');
    }

    private function resolveCompletedStats(User $courier): Collection
    {
        try {
            $completedStats = $this->completedStatsQuery()->forCourier($courier);
        } catch (\Throwable $exception) {
            $this->statsPaneUnavailable = true;

            Log::warning('my_orders_stats_render_failed', [
                'flow' => 'courier_cabinet',
                'courier_id' => $courier->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return collect();
        }

        $availableDates = $completedStats->pluck('date')->all();
        $this->expandedCompletedStatDates = array_values(array_intersect($this->expandedCompletedStatDates, $availableDates));

        if (! $this->hasInitializedCompletedStatsExpansion && $completedStats->isNotEmpty()) {
            $todayDate = now()->toDateString();

            if (in_array($todayDate, $availableDates, true)) {
                $this->expandedCompletedStatDates = [$todayDate];
            } elseif (! $this->hasUserInteractedWithCompletedStats && isset($availableDates[0])) {
                $this->expandedCompletedStatDates = [$availableDates[0]];
            }

            $this->hasInitializedCompletedStatsExpansion = true;
        }

        return $completedStats;
    }

    private function resolveNearbyAreaSummaryCached(User $courier): array
    {
        $ttlSeconds = max(5, (int) config('courier_runtime.my_orders.nearby_summary_ttl_seconds', 45));
        $cacheKey = sprintf('courier:%d:my-orders:nearby-area-summary', $courier->id);

        return Cache::remember(
            $cacheKey,
            now()->addSeconds($ttlSeconds),
            fn (): array => $this->resolveNearbyAreaSummary($courier),
        );
    }

    private function resolveNearbyAreaSummary(User $courier): array
    {
        $now = now();

        $uniqueNearbyOrders = OrderOffer::query()
            ->join('orders', 'orders.id', '=', 'order_offers.order_id')
            ->where('order_offers.courier_id', $courier->id)
            ->where('order_offers.status', OrderOffer::STATUS_PENDING)
            ->whereNotNull('order_offers.expires_at')
            ->where('order_offers.expires_at', '>', $now)
            ->whereNull('orders.expired_at')
            ->where(function ($query) use ($now): void {
                $query->whereNull('orders.valid_until_at')
                    ->orWhere('orders.valid_until_at', '>', $now);
            })
            ->groupBy('orders.id', 'orders.courier_payout_amount')
            ->selectRaw('orders.id, orders.courier_payout_amount');

        $summary = DB::query()
            ->fromSub($uniqueNearbyOrders, 'nearby_orders')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(nearby_orders.courier_payout_amount), 0) as total_earning')
            ->first();

        return [
            'orders_count' => (int) ($summary?->orders_count ?? 0),
            'total_earning' => (int) ($summary?->total_earning ?? 0),
        ];
    }

    private function defaultNearbyAreaSummary(): array
    {
        return [
            'orders_count' => 0,
            'total_earning' => 0,
        ];
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
        return $this->presenceService()->resolveAuthenticatedCourier();
    }

    private function presenceService(): CourierPresenceService
    {
        return app(CourierPresenceService::class);
    }

    private function navigationRuntime(): CourierNavigationRuntime
    {
        return app(CourierNavigationRuntime::class);
    }

    private function completedStatsQuery(): CourierCompletedOrdersDailyStatsQuery
    {
        return app(CourierCompletedOrdersDailyStatsQuery::class);
    }

    private function earningPreviewService(): OrderCourierNetEarningPreviewService
    {
        return app(OrderCourierNetEarningPreviewService::class);
    }

    private function isProofAware(Order $order): bool
    {
        return $order->completion_policy === Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM
            && $order->handover_type === Order::HANDOVER_DOOR;
    }

    private function hasAllProofs(Order $order): bool
    {
        $request = $order->completionRequest;
        if (! $request instanceof OrderCompletionRequest) {
            return false;
        }

        $proofTypes = $request->proofs->pluck('proof_type')->all();

        return in_array(OrderCompletionProof::TYPE_DOOR_PHOTO, $proofTypes, true)
            && in_array(OrderCompletionProof::TYPE_CONTAINER_PHOTO, $proofTypes, true);
    }

    public function proofPreviewUrl(?OrderCompletionProof $proof): ?string
    {
        if (! $proof) {
            return null;
        }

        return Storage::disk($proof->file_disk ?: 'public')->url($proof->file_path);
    }
}
