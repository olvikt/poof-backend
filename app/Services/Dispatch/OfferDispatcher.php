<?php

namespace App\Services\Dispatch;

use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
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

    public function dispatchForOrder(Order $order): ?OrderOffer
    {
        return $this->dispatchPrimaryOffer($order);
    }

    /* =========================================================
     | PRIMARY OFFER (UBER SEQUENTIAL)
     | Заказ крутится пока:
     | - status = searching
     | - courier_id = null
     ========================================================= */

    protected function dispatchPrimaryOffer(Order $order): ?OrderOffer
    {
        $startedAt = microtime(true);

        return DB::transaction(function () use ($order, $startedAt) {
            $now = now();

            /** @var Order|null $locked */
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            // Заказ уже не ищется или уже назначен
            if (! $locked || $locked->status !== Order::STATUS_SEARCHING || $locked->courier_id !== null) {
                return null;
            }

            if ($locked->isPromiseExpired()) {
                $this->orderAutoExpireService->expireOne((int) $locked->id, $now);

                return null;
            }

            $orderAgeSeconds = $locked->created_at ? $locked->created_at->diffInSeconds($now) : null;

            if ($locked->next_dispatch_at && $locked->next_dispatch_at->isFuture()) {
                Log::debug('dispatch_skipped_deferred_under_lock', [
                    'flow' => 'offer_dispatch',
                    'order_id' => $locked->id,
                    'dispatch_attempted' => false,
                    'dispatch_deferred' => true,
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
                Log::debug('dispatch_waiting_live_offer', [
                    'flow' => 'offer_dispatch',
                    'order_id' => $locked->id,
                    'dispatch_attempted' => false,
                    'dispatch_waiting_live_offer' => true,
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

            Log::debug('dispatch_started', [
                'flow' => 'offer_dispatch',
                'order_id' => $locked->id,
                'dispatch_attempted' => true,
                'attempt_count' => $attemptCount,
                'order_age_seconds' => $orderAgeSeconds,
            ]);

            /* -------------------------------------------------
             | 2) COURIER CANDIDATES (online + free)
             | ------------------------------------------------- */

            $orderHasCoords = $this->hasCoords($locked->lat, $locked->lng);

            $couriers = $this->fetchCandidates($locked, $orderHasCoords, $now);

            if ($couriers->isEmpty()) {
                $this->deferSearchingOrder(
                    orderId: (int) $locked->id,
                    attemptCount: $attemptCount,
                    now: $now,
                    reason: 'no_candidates',
                    orderAgeSeconds: $orderAgeSeconds,
                    startedAt: $startedAt,
                );

                Log::debug('dispatch_no_candidates', [
                    'flow' => 'offer_dispatch',
                    'order_id' => $locked->id,
                    'attempt_count' => $attemptCount,
                    'order_age_seconds' => $orderAgeSeconds,
                    'elapsed_ms' => $this->elapsedMs($startedAt),
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
                );

                Log::debug('dispatch_no_pick', [
                    'flow' => 'offer_dispatch',
                    'order_id' => $locked->id,
                    'candidate_count' => $couriers->count(),
                    'attempt_count' => $attemptCount,
                    'order_age_seconds' => $orderAgeSeconds,
                    'elapsed_ms' => $this->elapsedMs($startedAt),
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

            Log::info('dispatch_offer_created', [
                'flow' => 'offer_dispatch',
                'order_id' => $locked->id,
                'courier_id' => $picked->id,
                'offer_id' => $offer->id,
                'ttl_seconds' => $this->ttlSeconds,
                'candidate_count' => $couriers->count(),
                'attempt_count' => $attemptCount,
                'order_age_seconds' => $orderAgeSeconds,
                'elapsed_ms' => $this->elapsedMs($startedAt),
            ]);

            return $offer;
        });
    }

    /* =========================================================
     | DISPATCH LOOP (SCHEDULER SAFE)
     | ========================================================= */

    public function dispatchSearchingOrders(int $limit = 20): int
    {
        $count = 0;
        $now = now();

        $orders = $this->searchingOrdersQuery($now)
            ->select('id')
            ->orderByRaw('COALESCE(next_dispatch_at, created_at)')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($orders as $order) {
            if ($this->dispatchForOrder($order)) {
                $count++;
            }
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

    protected function deferSearchingOrder(
        int $orderId,
        int $attemptCount,
        Carbon $now,
        string $reason,
        ?int $orderAgeSeconds,
        float $startedAt,
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

                if ($distance > $this->primaryRadiusKm) {
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

        // 4) внутри окна сортируем: distance ASC, idle DESC, rotation DESC
        $winner = $window
            ->sort(function ($a, $b) {
                if ($a['distance'] !== $b['distance']) {
                    return $a['distance'] <=> $b['distance'];
                }
                if ($a['idle'] !== $b['idle']) {
                    return $b['idle'] <=> $a['idle'];
                }
                return $b['rotation'] <=> $a['rotation'];
            })
            ->first();

        return $winner['courier'] ?? null;
    }

    protected function fetchCandidates(Order $order, bool $orderHasCoords, $now): Collection
    {
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
                    ->whereIn('orders.status', [
                        Order::STATUS_ACCEPTED,
                        Order::STATUS_IN_PROGRESS,
                    ]);
            })
            ;

        if ($orderHasCoords) {
            [$latMin, $latMax, $lngMin, $lngMax] = $this->distanceBoundingBox(
                (float) $order->lat,
                (float) $order->lng,
                $this->primaryRadiusKm + $this->distanceWindowKm
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
            ])
            ->limit($this->maxCouriersToScan)
            ->get()
            ->map(function (stdClass $courier): stdClass {
                $courier->last_completed_at = $courier->last_completed_at ? Carbon::parse($courier->last_completed_at) : null;
                $courier->last_offer_at = $courier->last_offer_at ? Carbon::parse($courier->last_offer_at) : null;

                return $courier;
            });
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
}
