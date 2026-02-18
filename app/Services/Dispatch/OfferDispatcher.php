<?php

namespace App\Services\Dispatch;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OfferDispatcher
{
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
        return DB::transaction(function () use ($order) {

            /** @var Order|null $locked */
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            // Заказ уже не ищется или уже назначен
            if (! $locked || $locked->status !== Order::STATUS_SEARCHING || $locked->courier_id !== null) {
                return null;
            }

            /* -------------------------------------------------
             | 1) EXPIRE DEAD PENDING (zombie fix)
             | ------------------------------------------------- */
            OrderOffer::query()
                ->where('order_id', $locked->id)
                ->where('status', OrderOffer::STATUS_PENDING)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '<=', now());
                })
                ->update([
                    'status' => OrderOffer::STATUS_EXPIRED,
                ]);

            /* -------------------------------------------------
             | 2) COURIER CANDIDATES (online + free)
             | ------------------------------------------------- */

            $orderHasCoords = $this->hasCoords($locked->lat, $locked->lng);

            $couriers = User::query()
                ->where('role', User::ROLE_COURIER)
                ->where('is_active', true)
                ->where('is_online', true)
                ->where('is_busy', false)
                ->whereNotNull('last_lat')
                ->whereNotNull('last_lng')

                // не выполняет активный заказ
                ->whereDoesntHave('takenOrders', function ($q) {
                    $q->whereIn('status', [
                        Order::STATUS_ACCEPTED,
                        Order::STATUS_IN_PROGRESS,
                    ]);
                })

                // не слать повторно этому же курьеру ЖИВОЙ pending по этому заказу
                ->whereDoesntHave('orderOffers', function ($q) use ($locked) {
                    $q->where('order_id', $locked->id)
                      ->where('status', OrderOffer::STATUS_PENDING)
                      ->where('expires_at', '>', now());
                })

                ->limit($this->maxCouriersToScan)
                ->get();

            if ($couriers->isEmpty()) {
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
                return null;
            }

            /* -------------------------------------------------
             | 4) CREATE OFFER + ROTATION STAMP
             | ------------------------------------------------- */

            $offer = OrderOffer::create([
                'order_id'   => $locked->id,
                'courier_id' => $picked->id,
                'type'       => OrderOffer::TYPE_PRIMARY,
                'sequence'   => 1,
                'status'     => OrderOffer::STATUS_PENDING,
                'expires_at' => now()->addSeconds($this->ttlSeconds),
            ]);

            // отметка "когда последним разом показали оффер" (Rotation)
            $picked->update([
                'last_offer_at' => now(),
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

        $orders = Order::query()
            ->where('status', Order::STATUS_SEARCHING)
            ->whereNull('courier_id')
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        foreach ($orders as $order) {
            if ($this->dispatchForOrder($order)) {
                $count++;
            }
        }

        return $count;
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
    ): ?User {
        $now = now();

        // Если координат заказа нет — fallback: fairness по idle/rotation (без distance)
        if (! $orderHasCoords) {
            return $couriers
                ->sort(function (User $a, User $b) use ($now) {
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
            ->map(function (User $courier) use ($order, $now) {

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
