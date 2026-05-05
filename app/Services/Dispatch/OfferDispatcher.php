<?php

namespace App\Services\Dispatch;

use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Orders\OrderAutoExpireService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

class OfferDispatcher
{
    public function __construct(private readonly OrderAutoExpireService $orderAutoExpireService)
    {
    }

    /* =========================================================
     | CONFIG
     | ========================================================= */

    /** TTL оффера (секунды) */
    public int $ttlSeconds = 30;

    /** Радиус первичного оффера (км) */
    public float $primaryRadiusKm = 5.0;

    /**
     * Защита от "слишком большого distance":
     * Idle/Rotation учитываем ТОЛЬКО в пределах этого окна
     * (по сути "distance bucket").
     *
     * Пример: если лучший курьер в 0.9 км,
     * то мы будем сравнивать Idle/Rotation только среди курьеров <= 0.9+0.4 км.
     */
    public float $distanceWindowKm = 0.4;

    /** Safety limit */
    public int $maxCouriersToScan = 80;
    public int $dispatchBackoffBaseSeconds = 15;
    public int $dispatchBackoffMaxSeconds = 180;

    /* =========================================================
     | ENTRY
     | ========================================================= */

    public function dispatchForOrder(Order $order, string $triggerSource = 'direct'): ?OrderOffer
    {
        return $this->dispatchPrimaryOffer($order, $triggerSource);
    }

    /* =========================================================
     | PRIMARY OFFER (UBER SEQUENTIAL)
     | Заказ крутится пока:
     | - status = searching
     | - courier_id = null
     ========================================================= */

    protected function dispatchPrimaryOffer(Order $order, string $triggerSource): ?OrderOffer
    {
        $startedAt = microtime(true);

        return DB::transaction(function () use ($order, $startedAt, $triggerSource) {
            $now = now();

            /** @var Order|null $locked */
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            // Заказ уже не ищется или уже назначен
            if (! $locked || $locked->status !== Order::STATUS_SEARCHING || $locked->courier_id !== null) {
                Log::debug('dispatch_skipped', [
                    'order_id' => $order->id,
                    'subscription_id' => $order->subscription_id !== null ? (int) $order->subscription_id : null,
                    'status' => (string) ($locked?->status ?? 'missing'),
                    'reason' => $locked === null ? 'order_not_found_under_lock' : 'order_not_dispatchable_state',
                    'trigger_source' => $triggerSource,
                    'order_age_seconds' => null,
                    'elapsed_ms' => $this->elapsedMs($startedAt),
                ]);
                return null;
            }

            if ($locked->isPromiseExpired()) {
                $this->orderAutoExpireService->expireOne((int) $locked->id, $now);
                Log::debug('dispatch_skipped', [
                    'order_id' => $locked->id,
                    'subscription_id' => $locked->subscription_id !== null ? (int) $locked->subscription_id : null,
                    'status' => (string) $locked->status,
                    'reason' => 'order_promise_expired',
                    'trigger_source' => $triggerSource,
                    'order_age_seconds' => $locked->created_at ? $locked->created_at->diffInSeconds($now) : null,
                    'elapsed_ms' => $this->elapsedMs($startedAt),
                ]);

                return null;
            }

            $orderAgeSeconds = $locked->created_at ? $locked->created_at->diffInSeconds($now) : null;

            if ($locked->next_dispatch_at && $locked->next_dispatch_at->isFuture()) {
                Log::debug('dispatch_skipped', [
                    'order_id' => $locked->id,
                    'subscription_id' => $locked->subscription_id !== null ? (int) $locked->subscription_id : null,
                    'status' => (string) $locked->status,
                    'reason' => 'next_dispatch_at_in_future',
                    'trigger_source' => $triggerSource,
                    'dispatch_backoff_until' => $locked->next_dispatch_at->toIso8601String(),
                    'order_age_seconds' => $orderAgeSeconds,
                    'elapsed_ms' => $this->elapsedMs($startedAt),
                ]);

                return null;
            }

            /* -------------------------------------------------
             | 1) EXPIRE DEAD PENDING (zombie fix)
             | ------------------------------------------------- */
            OrderOffer::query()
                ->where('order_id', $locked->id)
                ->where('status', OrderOffer::STATUS_PENDING)
                ->where(function ($q) use ($now): void {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '<=', $now);
                })
                ->update([
                    'status' => OrderOffer::STATUS_EXPIRED,
                ]);

            if ($this->hasLivePendingOffer((int) $locked->id, $now)) {
                Log::debug('dispatch_skipped', [
                    'order_id' => $locked->id,
                    'subscription_id' => $locked->subscription_id !== null ? (int) $locked->subscription_id : null,
                    'status' => (string) $locked->status,
                    'reason' => 'waiting_live_offer',
                    'trigger_source' => $triggerSource,
                    'order_age_seconds' => $orderAgeSeconds,
                    'elapsed_ms' => $this->elapsedMs($startedAt),
                ]);

                return null;
            }

            $attemptCount = ((int) ($locked->dispatch_attempts ?? 0)) + 1;

            DB::table('orders')
                ->where('id', $locked->id)
                ->update([
                    'last_dispatch_attempt_at' => $now,
                    'dispatch_attempts' => $attemptCount,
                ]);
            $locked->forceFill(['dispatch_attempts' => $attemptCount]);

            Log::debug('dispatch_started', [
                'order_id' => $locked->id,
                'subscription_id' => $locked->subscription_id !== null ? (int) $locked->subscription_id : null,
                'status' => (string) $locked->status,
                'reason' => null,
                'attempt_count' => $attemptCount,
                'trigger_source' => $triggerSource,
                'order_age_seconds' => $orderAgeSeconds,
                'elapsed_ms' => $this->elapsedMs($startedAt),
            ]);

            /* -------------------------------------------------
             | 2) COURIER CANDIDATES (online + free)
             | ------------------------------------------------- */

            $orderHasCoords = $this->hasCoords($locked->lat, $locked->lng);

            $candidateFetchStartedAt = microtime(true);
            $couriers = $this->fetchCandidates($locked, $orderHasCoords, $now);
            $candidateScanCount = $couriers->count();

            Log::debug('dispatch_candidates_evaluated', [
                'flow' => 'offer_dispatch',
                'order_id' => $locked->id,
                'attempt_count' => $attemptCount,
                'trigger_source' => $triggerSource,
                'search_radius_km' => $this->dispatchRadiusKmForOrder($locked),
                'bbox_prefilter_applied' => $orderHasCoords,
                'candidate_scan_count' => $candidateScanCount,
                'candidate_count' => $candidateScanCount,
                'elapsed_ms' => $this->elapsedMs($candidateFetchStartedAt),
            ]);

            if ($couriers->isEmpty()) {
                $reasonBreakdown = $this->candidateReasonBreakdown($locked, $orderHasCoords, $now);

                $this->deferSearchingOrder(
                    orderId: (int) $locked->id,
                    attemptCount: $attemptCount,
                    now: $now,
                    reason: 'no_candidates',
                    orderAgeSeconds: $orderAgeSeconds,
                    startedAt: $startedAt,
                    triggerSource: $triggerSource,
                );

                Log::info('offer_not_created', [
                    'order_id' => $locked->id,
                    'subscription_id' => $locked->subscription_id !== null ? (int) $locked->subscription_id : null,
                    'status' => (string) $locked->status,
                    'reason' => 'no_candidates',
                    'attempt_count' => $attemptCount,
                    'reason_breakdown' => $reasonBreakdown['reason_breakdown'],
                    'search_radius_km' => $this->dispatchRadiusKmForOrder($locked),
                    'bbox_prefilter_applied' => $orderHasCoords,
                    'candidate_scan_count' => $candidateScanCount,
                    'candidate_count' => 0,
                    'diagnostic_candidate_scan_count' => $reasonBreakdown['candidate_scan_count'],
                    'trigger_source' => $triggerSource,
                    'order_age_seconds' => $orderAgeSeconds,
                    'elapsed_ms' => $this->elapsedMs($startedAt),
                    'counter' => 'offer_not_created_total',
                    'counter_increment' => 1,
                ]);
                return null;
            }

            /* -------------------------------------------------
             | 3) PICK COURIER (Distance → Idle → Rotation)
             | + distance guard (bucket/window)
             | ------------------------------------------------- */

            $picked = $this->pickCourierUberStyle(
                $couriers,
                $locked,
                $orderHasCoords
            );

            if (! $picked) {
                $this->deferSearchingOrder(
                    orderId: (int) $locked->id,
                    attemptCount: $attemptCount,
                    now: $now,
                    reason: 'no_pick',
                    orderAgeSeconds: $orderAgeSeconds,
                    startedAt: $startedAt,
                    triggerSource: $triggerSource,
                );

                Log::info('offer_not_created', [
                    'order_id' => $locked->id,
                    'subscription_id' => $locked->subscription_id !== null ? (int) $locked->subscription_id : null,
                    'status' => (string) $locked->status,
                    'reason' => 'no_pick',
                    'attempt_count' => $attemptCount,
                    'trigger_source' => $triggerSource,
                    'search_radius_km' => $this->dispatchRadiusKmForOrder($locked),
                    'bbox_prefilter_applied' => $orderHasCoords,
                    'candidate_scan_count' => $candidateScanCount,
                    'candidate_count' => $couriers->count(),
                    'order_age_seconds' => $orderAgeSeconds,
                    'elapsed_ms' => $this->elapsedMs($startedAt),
                    'counter' => 'offer_not_created_total',
                    'counter_increment' => 1,
                ]);
                return null;
            }

            /* -------------------------------------------------
             | 4) CREATE OFFER + ROTATION STAMP
             | ------------------------------------------------- */

            $offer = OrderOffer::createPrimaryPending(
                orderId: (int) $locked->id,
                courierId: (int) $picked->id,
                ttlSeconds: $this->ttlSeconds,
            );

            // отметка "когда последним разом показали оффер" (Rotation)
            DB::table('users')
                ->where('id', (int) $picked->id)
                ->update([
                    'last_offer_at' => now(),
                ]);

            DB::table('orders')
                ->where('id', (int) $locked->id)
                ->update([
                    'next_dispatch_at' => null,
                ]);

            Log::info('offer_created', [
                'order_id' => $locked->id,
                'subscription_id' => $locked->subscription_id !== null ? (int) $locked->subscription_id : null,
                'status' => (string) $locked->status,
                'reason' => null,
                'courier_id' => $picked->id,
                'offer_id' => $offer->id,
                'attempt_count' => $attemptCount,
                'trigger_source' => $triggerSource,
                'search_radius_km' => $this->dispatchRadiusKmForOrder($locked),
                'bbox_prefilter_applied' => $orderHasCoords,
                'candidate_scan_count' => $candidateScanCount,
                'candidate_count' => $couriers->count(),
                'order_age_seconds' => $orderAgeSeconds,
                'elapsed_ms' => $this->elapsedMs($startedAt),
                'counter' => 'offer_created_total',
                'counter_increment' => 1,
            ]);

            return $offer;
        });
    }

    /* =========================================================
     | DISPATCH LOOP (SCHEDULER SAFE)
     | ========================================================= */

    public function dispatchSearchingOrders(int $limit = 20): int
    {
        $startedAt = microtime(true);
        $count = 0;
        $now = now();

        $orders = $this->dispatchQueueSelection($now, $limit);
        $selected = $orders->count();

        foreach ($orders as $order) {
            if ($this->dispatchForOrder($order, 'scheduler_loop')) {
                $count++;
            }
        }

        $noopCount = max(0, $selected - $count);

        Log::info('dispatch_queue_batch_processed', [
            'flow' => 'offer_dispatch',
            'selected_orders' => $selected,
            'offers_created' => $count,
            'noop_attempts' => $noopCount,
            'noop_ratio' => $selected > 0 ? round($noopCount / $selected, 4) : 0.0,
            'limit' => $limit,
            'elapsed_ms' => $this->elapsedMs($startedAt),
            'counter' => 'dispatch_queue_batch_processed_total',
            'counter_increment' => 1,
            'counter_labels' => [
                'had_selected_orders' => $selected > 0 ? 'yes' : 'no',
            ],
        ]);

        if ($noopCount > 0) {
            Log::info('dispatch_queue_noop_ratio_observed', [
                'flow' => 'offer_dispatch',
                'selected_orders' => $selected,
                'noop_attempts' => $noopCount,
                'noop_ratio' => $selected > 0 ? round($noopCount / $selected, 4) : 0.0,
                'counter' => 'dispatch_queue_noop_total',
                'counter_increment' => $noopCount,
            ]);
        }

        return $count;
    }

    protected function searchingOrdersQuery(Carbon $now)
    {
        return Order::query()
            ->where('status', Order::STATUS_SEARCHING)
            ->whereNull('courier_id')
            ->where('payment_status', Order::PAY_PAID)
            ->whereNull('expired_at')
            ->where(function ($q) use ($now): void {
                $q->whereNull('valid_until_at')
                    ->orWhere('valid_until_at', '>', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('next_dispatch_at')
                    ->orWhere('next_dispatch_at', '<=', $now);
            });
    }

    protected function dispatchQueueSelection(Carbon $now, int $limit): Collection
    {
        $dueDeferred = $this->searchingOrdersQuery($now)
            ->whereNotNull('next_dispatch_at')
            ->select('id')
            ->orderBy('next_dispatch_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $remaining = max(0, $limit - $dueDeferred->count());

        if ($remaining === 0) {
            return $dueDeferred;
        }

        $brandNew = $this->searchingOrdersQuery($now)
            ->whereNull('next_dispatch_at')
            ->select('id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($remaining)
            ->get();

        return $dueDeferred->concat($brandNew);
    }

    protected function deferSearchingOrder(
        int $orderId,
        int $attemptCount,
        Carbon $now,
        string $reason,
        ?int $orderAgeSeconds,
        float $startedAt,
        string $triggerSource,
    ): void {
        $backoffSeconds = $this->backoffSeconds($attemptCount);
        $backoffUntil = $now->copy()->addSeconds($backoffSeconds);

        DB::table('orders')
            ->where('id', $orderId)
            ->update([
                'next_dispatch_at' => $backoffUntil,
            ]);

        Log::debug('dispatch_deferred', [
            'flow' => 'offer_dispatch',
            'order_id' => $orderId,
            'reason' => $reason,
            'trigger_source' => $triggerSource,
            'dispatch_deferred' => true,
            'dispatch_backoff_until' => $backoffUntil->toIso8601String(),
            'attempt_count' => $attemptCount,
            'order_age_seconds' => $orderAgeSeconds,
            'elapsed_ms' => $this->elapsedMs($startedAt),
        ]);
    }

    protected function hasLivePendingOffer(int $orderId, Carbon $now): bool
    {
        return OrderOffer::query()
            ->where('order_id', $orderId)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->where('expires_at', '>', $now)
            ->exists();
    }

    protected function backoffSeconds(int $attemptCount): int
    {
        $exponent = max(0, min($attemptCount - 1, 6));
        $seconds = $this->dispatchBackoffBaseSeconds * (2 ** $exponent);

        return (int) min($seconds, $this->dispatchBackoffMaxSeconds);
    }

    /* =========================================================
     | PICKING (UBER STYLE)
     | 1) фильтруем по радиусу
     | 2) находим минимальную дистанцию
     | 3) ограничиваем кандидатов "окном" distanceWindowKm
     | 4) среди окна сортируем: idle desc, rotation desc
     ========================================================= */

    protected function pickCourierUberStyle(
        Collection $couriers,
        Order $order,
        bool $orderHasCoords
    ): ?stdClass {
        $now = now();

        // Если координат заказа нет — fallback: fairness по idle/rotation (без distance)
        if (! $orderHasCoords) {
            return $couriers
                ->sort(function (stdClass $a, stdClass $b) use ($now) {
                    $aIdle = $a->last_completed_at ? $a->last_completed_at->diffInMinutes($now) : 9999;
                    $bIdle = $b->last_completed_at ? $b->last_completed_at->diffInMinutes($now) : 9999;

                    if ($aIdle !== $bIdle) return $bIdle <=> $aIdle;

                    $aRot = $a->last_offer_at ? $a->last_offer_at->diffInMinutes($now) : 9999;
                    $bRot = $b->last_offer_at ? $b->last_offer_at->diffInMinutes($now) : 9999;

                    return $bRot <=> $aRot;
                })
                ->first();
        }

        // 1) считаем дистанции и отбрасываем тех, кто дальше радиуса
        $scored = $couriers
            ->map(function (stdClass $courier) use ($order, $now) {

                if (! $this->hasCoords($courier->last_lat, $courier->last_lng)) {
                    return null;
                }

                $distance = $this->haversineKm(
                    (float) $courier->last_lat,
                    (float) $courier->last_lng,
                    (float) $order->lat,
                    (float) $order->lng
                );

                if ($distance > $this->dispatchRadiusKmForOrder($order)) {
                    return null;
                }

                $idle = $courier->last_completed_at
                    ? $courier->last_completed_at->diffInMinutes($now)
                    : 9999;

                $rotation = $courier->last_offer_at
                    ? $courier->last_offer_at->diffInMinutes($now)
                    : 9999;

                return [
                    'courier'   => $courier,
                    'distance'  => $distance,
                    'idle'      => $idle,
                    'rotation'  => $rotation,
                    'workload_today' => (int) ($courier->workload_today ?? 0),
                ];
            })
            ->filter()
            ->values();

        if ($scored->isEmpty()) {
            return null;
        }

        // 2) находим минимальную дистанцию
        $minDistance = (float) $scored->min('distance');

        // 3) защита от "слишком большого distance":
        // учитываем idle/rotation только среди близких к minDistance
        $windowMax = $minDistance + $this->distanceWindowKm;

        $window = $scored
            ->filter(fn ($x) => (float) $x['distance'] <= $windowMax)
            ->values();

        // 4) внутри окна сортируем по score: distance + workload_penalty + recency_penalty
        $winner = $window
            ->sort(function ($a, $b) {
                $aScore = ($a['distance'] * (float) config('dispatch.fairness.distance_weight', 1.0))
                    + ($a['workload_today'] * (float) config('dispatch.fairness.workload_penalty_weight', 0.6))
                    + ((1 / max(1, $a['rotation'])) * (float) config('dispatch.fairness.recency_penalty_weight', 0.2));
                $bScore = ($b['distance'] * (float) config('dispatch.fairness.distance_weight', 1.0))
                    + ($b['workload_today'] * (float) config('dispatch.fairness.workload_penalty_weight', 0.6))
                    + ((1 / max(1, $b['rotation'])) * (float) config('dispatch.fairness.recency_penalty_weight', 0.2));

                if ($aScore !== $bScore) {
                    return $aScore <=> $bScore;
                }
                return $b['rotation'] <=> $a['rotation'];
            })
            ->first();

        return $winner['courier'] ?? null;
    }

    protected function fetchCandidates(Order $order, bool $orderHasCoords, $now): Collection
    {
        $maxAttempts = (int) config('dispatch.fairness.max_offer_attempts_per_courier', 3);
        $cooldownMinutes = (int) config('dispatch.fairness.reoffer_cooldown_minutes', 5);
        $query = DB::table('users')
            ->join('couriers', 'couriers.user_id', '=', 'users.id')
            ->where('users.role', 'courier')
            ->where('users.is_active', true)
            ->whereNotNull('users.last_lat')
            ->whereNotNull('users.last_lng')
            ->where('couriers.status', Courier::STATUS_ONLINE)
            ->where('couriers.last_location_at', '>', $now->copy()->subSeconds((int) config('courier_runtime.freshness.dispatch_candidate_location_seconds', 60)))
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('orders')
                    ->whereColumn('orders.courier_id', 'users.id')
                    ->where(function ($blocking): void {
                        $blocking
                            ->where('orders.status', Order::STATUS_ACCEPTED)
                            ->orWhere(function ($inProgress): void {
                                $inProgress
                                    ->where('orders.status', Order::STATUS_IN_PROGRESS)
                                    ->whereNotExists(function ($completion): void {
                                        $completion->selectRaw('1')
                                            ->from('order_completion_requests')
                                            ->whereColumn('order_completion_requests.order_id', 'orders.id')
                                            ->whereIn('order_completion_requests.status', [
                                                \App\Models\OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION,
                                                \App\Models\OrderCompletionRequest::STATUS_DISPUTED,
                                            ]);
                                    });
                            });
                    });
            })
            ->whereNotExists(function ($sub) use ($order, $maxAttempts): void {
                $sub->selectRaw('1')
                    ->from('order_offers')
                    ->whereColumn('order_offers.courier_id', 'users.id')
                    ->where('order_offers.order_id', $order->id)
                    ->whereIn('order_offers.status', [OrderOffer::STATUS_DECLINED, OrderOffer::STATUS_EXPIRED])
                    ->groupBy('order_offers.courier_id')
                    ->havingRaw('COUNT(*) >= ?', [$maxAttempts]);
            })
            ->whereNotExists(function ($sub) use ($order, $now, $cooldownMinutes): void {
                $sub->selectRaw('1')
                    ->from('order_offers')
                    ->whereColumn('order_offers.courier_id', 'users.id')
                    ->where('order_offers.order_id', $order->id)
                    ->whereIn('order_offers.status', [OrderOffer::STATUS_DECLINED, OrderOffer::STATUS_EXPIRED])
                    ->whereRaw('COALESCE(order_offers.last_offered_at, order_offers.created_at) > ?', [$now->copy()->subMinutes($cooldownMinutes)]);
            })
            ;

        if ($orderHasCoords) {
            [$latMin, $latMax, $lngMin, $lngMax] = $this->distanceBoundingBox(
                (float) $order->lat,
                (float) $order->lng,
                $this->dispatchRadiusKmForOrder($order) + $this->distanceWindowKm
            );

            $query
                ->whereBetween('users.last_lat', [$latMin, $latMax])
                ->whereBetween('users.last_lng', [$lngMin, $lngMax]);
        }

        return $query
            ->select([
                'users.id',
                'users.last_lat',
                'users.last_lng',
                'users.last_completed_at',
                'users.last_offer_at',
                DB::raw("(select count(*) from orders o where o.courier_id = users.id and o.status = 'done' and date(o.completed_at) = current_date) as workload_today"),
            ])
            ->limit($this->maxCouriersToScan)
            ->get()
            ->map(function (stdClass $courier): stdClass {
                $courier->last_completed_at = $courier->last_completed_at ? Carbon::parse($courier->last_completed_at) : null;
                $courier->last_offer_at = $courier->last_offer_at ? Carbon::parse($courier->last_offer_at) : null;

                return $courier;
            });
    }

    protected function candidateReasonBreakdown(Order $order, bool $orderHasCoords, Carbon $now): array
    {
        $baseScanLimit = $this->maxCouriersToScan * 2;
        $maxAttempts = (int) config('dispatch.fairness.max_offer_attempts_per_courier', 3);
        $cooldownThreshold = $now->copy()->subMinutes((int) config('dispatch.fairness.reoffer_cooldown_minutes', 5));
        $staleThreshold = $now->copy()->subSeconds((int) config('courier_runtime.freshness.dispatch_candidate_location_seconds', 60));
        $alivePendingCourierIds = OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->pluck('courier_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $busyCourierIds = Order::query()
            ->runtimeBlockingForCourier()
            ->whereNotNull('courier_id')
            ->pluck('courier_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $scan = DB::table('users')
            ->leftJoin('couriers', 'couriers.user_id', '=', 'users.id')
            ->select([
                'users.id',
                'users.role',
                'users.is_active',
                'users.last_lat',
                'users.last_lng',
                'couriers.status as courier_status',
                'couriers.last_location_at',
            ])
            ->orderBy('users.id')
            ->limit($baseScanLimit)
            ->get();

        $reasons = [
            'inactive_user' => 0,
            'wrong_role' => 0,
            'courier_offline' => 0,
            'stale_location' => 0,
            'busy_active_order' => 0,
            'duplicate_alive_pending' => 0,
            'outside_bbox' => 0,
            'missing_coordinates' => 0,
            'rejected_recently' => 0,
            'max_attempts_reached' => 0,
            'cooldown_active' => 0,
        ];

        [$latMin, $latMax, $lngMin, $lngMax] = $orderHasCoords
            ? $this->distanceBoundingBox((float) $order->lat, (float) $order->lng, $this->dispatchRadiusKmForOrder($order) + $this->distanceWindowKm)
            : [null, null, null, null];

        foreach ($scan as $row) {
            if (! (bool) $row->is_active) {
                $reasons['inactive_user']++;
                continue;
            }

            if ($row->role !== User::ROLE_COURIER) {
                $reasons['wrong_role']++;
                continue;
            }

            if ($row->courier_status !== Courier::STATUS_ONLINE) {
                $reasons['courier_offline']++;
                continue;
            }

            if (! $row->last_location_at || Carbon::parse($row->last_location_at)->lte($staleThreshold)) {
                $reasons['stale_location']++;
                continue;
            }

            if (in_array((int) $row->id, $busyCourierIds, true)) {
                $reasons['busy_active_order']++;
                continue;
            }

            if (in_array((int) $row->id, $alivePendingCourierIds, true)) {
                $reasons['duplicate_alive_pending']++;
                continue;
            }

            $attempts = OrderOffer::query()
                ->where('order_id', $order->id)
                ->where('courier_id', (int) $row->id)
                ->whereIn('status', [OrderOffer::STATUS_DECLINED, OrderOffer::STATUS_EXPIRED])
                ->count();
            if ($attempts >= $maxAttempts) {
                $reasons['max_attempts_reached']++;
                continue;
            }

            $latestRejected = OrderOffer::query()
                ->where('order_id', $order->id)
                ->where('courier_id', (int) $row->id)
                ->whereIn('status', [OrderOffer::STATUS_DECLINED, OrderOffer::STATUS_EXPIRED])
                ->latest('last_offered_at')
                ->first();
            $lastOfferedAt = $latestRejected?->last_offered_at ?? $latestRejected?->created_at;
            if ($lastOfferedAt && $lastOfferedAt->gt($cooldownThreshold)) {
                $reasons['rejected_recently']++;
                $reasons['cooldown_active']++;
                continue;
            }

            if (! $this->hasCoords($row->last_lat, $row->last_lng)) {
                $reasons['missing_coordinates']++;
                continue;
            }

            if ($orderHasCoords) {
                if ((float) $row->last_lat < $latMin || (float) $row->last_lat > $latMax || (float) $row->last_lng < $lngMin || (float) $row->last_lng > $lngMax) {
                    $reasons['outside_bbox']++;
                }
            }
        }

        return [
            'reason_breakdown' => $reasons,
            'candidate_scan_count' => $scan->count(),
        ];
    }

    protected function distanceBoundingBox(float $lat, float $lng, float $radiusKm): array
    {
        $latDelta = $radiusKm / 111.0;
        $lngDivisor = max(cos(deg2rad($lat)), 0.01);
        $lngDelta = $radiusKm / (111.0 * $lngDivisor);

        return [
            $lat - $latDelta,
            $lat + $latDelta,
            $lng - $lngDelta,
            $lng + $lngDelta,
        ];
    }

    protected function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /* =========================================================
     | HELPERS
     | ========================================================= */

    protected function hasCoords($lat, $lng): bool
    {
        return is_numeric($lat)
            && is_numeric($lng)
            && (float) $lat !== 0.0
            && (float) $lng !== 0.0;
    }

    protected function haversineKm(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $r = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a =
            sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) *
            cos(deg2rad($lat2)) *
            sin($dLon / 2) ** 2;

        return $r * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    protected function dispatchRadiusKmForOrder(Order $order): float
    {
        $attempts = (int) ($order->dispatch_attempts ?? 0);
        $stepSeconds = max(1, (int) config('dispatch.fairness.starvation_step_seconds', 120));
        $step = max(0, intdiv($attempts * $this->dispatchBackoffBaseSeconds, $stepSeconds));
        $extra = min(
            $step * (float) config('dispatch.fairness.starvation_radius_step_km', 1.5),
            (float) config('dispatch.fairness.starvation_max_extra_radius_km', 10.0),
        );

        return $this->primaryRadiusKm + $extra;
    }
}
