<?php

declare(strict_types=1);

namespace App\Services\Dispatch;

use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchDiagnosticsService
{
    public function findStuckSearchingOrders(int $limit = 100, ?Carbon $now = null): array
    {
        $now ??= now();

        $futureGraceMinutes = max(1, (int) config('courier_runtime.searching_diagnostics.future_grace_minutes', 10));
        $maxSearchingAgeMinutes = max(1, (int) config('courier_runtime.searching_diagnostics.max_searching_age_minutes', 20));
        $farFutureThreshold = $now->copy()->addMinutes($futureGraceMinutes);
        $oldThreshold = $now->copy()->subMinutes($maxSearchingAgeMinutes);

        /** @var EloquentCollection<int,Order> $orders */
        $orders = Order::query()
            ->where('status', Order::STATUS_SEARCHING)
            ->where('payment_status', Order::PAY_PAID)
            ->whereNull('courier_id')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $stuck = [];
        $classified = [];

        foreach ($orders as $order) {
            $isInvalid = $order->expired_at !== null || $order->isPromiseExpired();
            $farFuture = $order->next_dispatch_at?->gt($farFutureThreshold) ?? false;
            $overdue = $order->created_at?->lte($oldThreshold) ?? false;

            $row = [
                'order_id' => $order->id,
                'created_at' => $order->created_at?->toIso8601String(),
                'age_minutes' => $order->created_at ? $order->created_at->diffInMinutes($now) : null,
                'next_dispatch_at' => $order->next_dispatch_at?->toIso8601String(),
                'dispatch_attempts' => (int) ($order->dispatch_attempts ?? 0),
                'last_dispatch_attempt_at' => $order->last_dispatch_attempt_at?->toIso8601String(),
                'valid_until_at' => $order->valid_until_at?->toIso8601String(),
                'is_expired' => $isInvalid,
                'last_no_candidates_reason' => null,
                'anomaly_reason' => $farFuture ? 'next_dispatch_far_future' : ($overdue ? 'searching_age_overdue' : null),
            ];

            if ($isInvalid) {
                $row['classification'] = 'invalid_or_expired';
                $classified[] = $row;
                continue;
            }

            if (! $farFuture && ! $overdue) {
                continue;
            }

            $stuck[] = $row;

            Log::warning('searching_order_stuck_detected', [
                'order_id' => $order->id,
                'anomaly_reason' => $row['anomaly_reason'],
                'next_dispatch_at' => $row['next_dispatch_at'],
                'dispatch_attempts' => $row['dispatch_attempts'],
                'last_dispatch_attempt_at' => $row['last_dispatch_attempt_at'],
                'valid_until_at' => $row['valid_until_at'],
                'counter' => 'searching_orders_stuck_total',
                'counter_increment' => 1,
            ]);
        }

        return [
            'stuck' => $stuck,
            'classified' => $classified,
            'thresholds' => [
                'future_grace_minutes' => $futureGraceMinutes,
                'max_searching_age_minutes' => $maxSearchingAgeMinutes,
            ],
        ];
    }

    public function diagnoseOrder(Order $order, ?Carbon $now = null): array
    {
        $now ??= now();

        $livePending = OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->count();

        $expired = $order->expired_at !== null || $order->isPromiseExpired();
        $eligible = $order->isDispatchableForOfferPipeline();
        $scan = $this->candidateScanSummary($order, $now);

        return [
            'order_id' => $order->id,
            'dispatch_eligibility' => $eligible ? 'eligible' : 'not_eligible',
            'eligibility_reasons' => $this->orderEligibilityReasons($order, $now),
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'courier_id' => $order->courier_id,
            'dispatch_attempts' => (int) ($order->dispatch_attempts ?? 0),
            'next_dispatch_at' => $order->next_dispatch_at?->toIso8601String(),
            'last_dispatch_attempt_at' => $order->last_dispatch_attempt_at?->toIso8601String(),
            'valid_until_at' => $order->valid_until_at?->toIso8601String(),
            'expired_at' => $order->expired_at?->toIso8601String(),
            'is_expired' => $expired,
            'expired_reason' => $order->expired_reason,
            'live_pending_offer_count' => $livePending,
            'has_live_pending_offer' => $livePending > 0,
            'recent_exclusion_breakdown' => $scan['reason_breakdown'],
            'candidate_scan_summary' => $scan,
        ];
    }

    public function diagnoseCourierForOrder(Order $order, User $courier, ?Carbon $now = null): array
    {
        $now ??= now();
        $courier->loadMissing('courierProfile');

        $staleThreshold = $now->copy()->subSeconds((int) config('courier_runtime.freshness.dispatch_candidate_location_seconds', 60));
        $hasActiveOrder = Order::query()
            ->where('courier_id', $courier->id)
            ->whereIn('status', [Order::STATUS_ACCEPTED, Order::STATUS_IN_PROGRESS])
            ->exists();

        $hasDuplicateLivePending = OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('courier_id', $courier->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->exists();

        $insideBbox = $this->isInsideBbox($order, $courier);

        $rules = [
            'user_active' => (bool) $courier->is_active,
            'role_ok' => $courier->role === User::ROLE_COURIER,
            'courier_status_online' => (string) optional($courier->courierProfile)->status === Courier::STATUS_ONLINE,
            'location_fresh' => optional($courier->courierProfile)->last_location_at?->gt($staleThreshold) ?? false,
            'coordinates_present' => $courier->last_lat !== null && $courier->last_lng !== null,
            'active_order_conflict' => ! $hasActiveOrder,
            'duplicate_live_pending' => ! $hasDuplicateLivePending,
            'inside_bbox' => $insideBbox,
        ];

        $failed = array_keys(array_filter($rules, static fn (bool $pass): bool => $pass === false));

        return [
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'verdict' => $failed === [] ? 'eligible' : 'not_eligible',
            'failed_rules' => $failed,
            'rules' => $rules,
            'courier_status' => (string) optional($courier->courierProfile)->status,
            'last_location_at' => optional($courier->courierProfile)->last_location_at?->toIso8601String(),
        ];
    }

    public function candidateScanSummary(Order $order, ?Carbon $now = null): array
    {
        $now ??= now();
        $baseScanLimit = max(1, (int) config('courier_runtime.searching_diagnostics.candidate_scan_limit', 160));
        $staleThreshold = $now->copy()->subSeconds((int) config('courier_runtime.freshness.dispatch_candidate_location_seconds', 60));

        $alivePendingCourierIds = OrderOffer::query()
            ->where('order_id', $order->id)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->pluck('courier_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $busyCourierIds = Order::query()
            ->whereIn('status', [Order::STATUS_ACCEPTED, Order::STATUS_IN_PROGRESS])
            ->whereNotNull('courier_id')
            ->pluck('courier_id')
            ->map(fn ($id): int => (int) $id)
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
            'eligible' => 0,
        ];

        [$latMin, $latMax, $lngMin, $lngMax] = $this->orderBoundingBox($order);
        $hasOrderCoords = $order->lat !== null && $order->lng !== null;

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

            if ($row->last_lat === null || $row->last_lng === null) {
                $reasons['missing_coordinates']++;
                continue;
            }

            if ($hasOrderCoords && ((float) $row->last_lat < $latMin || (float) $row->last_lat > $latMax || (float) $row->last_lng < $lngMin || (float) $row->last_lng > $lngMax)) {
                $reasons['outside_bbox']++;
                continue;
            }

            $reasons['eligible']++;
        }

        return [
            'reason_breakdown' => $reasons,
            'candidate_scan_count' => $scan->count(),
        ];
    }

    private function orderEligibilityReasons(Order $order, Carbon $now): array
    {
        $reasons = [];

        if ($order->status !== Order::STATUS_SEARCHING) {
            $reasons[] = 'status_not_searching';
        }
        if ($order->payment_status !== Order::PAY_PAID) {
            $reasons[] = 'payment_not_paid';
        }
        if ($order->courier_id !== null) {
            $reasons[] = 'already_assigned';
        }
        if ($order->expired_at !== null) {
            $reasons[] = 'expired_at_set';
        }
        if ($order->valid_until_at && $order->valid_until_at->lte($now)) {
            $reasons[] = 'validity_expired';
        }

        return $reasons;
    }

    private function orderBoundingBox(Order $order): array
    {
        if ($order->lat === null || $order->lng === null) {
            return [null, null, null, null];
        }

        $radiusKm = 5.0 + 0.4;
        $lat = (float) $order->lat;
        $lng = (float) $order->lng;

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

    private function isInsideBbox(Order $order, User $courier): bool
    {
        if ($order->lat === null || $order->lng === null) {
            return true;
        }

        if ($courier->last_lat === null || $courier->last_lng === null) {
            return false;
        }

        [$latMin, $latMax, $lngMin, $lngMax] = $this->orderBoundingBox($order);

        return (float) $courier->last_lat >= (float) $latMin
            && (float) $courier->last_lat <= (float) $latMax
            && (float) $courier->last_lng >= (float) $lngMin
            && (float) $courier->last_lng <= (float) $lngMax;
    }
}
