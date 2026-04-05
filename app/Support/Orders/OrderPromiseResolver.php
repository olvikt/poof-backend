<?php

declare(strict_types=1);

namespace App\Support\Orders;

use App\Models\Order;
use Illuminate\Support\Carbon;

class OrderPromiseResolver
{
    public function resolveCreateAttributes(array $attributes, ?Carbon $now = null): array
    {
        $now ??= now();

        $serviceMode = (string) ($attributes['service_mode'] ?? $this->inferServiceMode($attributes));
        $waitPreference = (string) ($attributes['client_wait_preference'] ?? config('order_promise.default_wait_preference', Order::WAIT_AUTO_CANCEL_IF_NOT_FOUND));

        $windowFrom = null;
        $windowTo = null;

        if ($serviceMode === Order::SERVICE_MODE_PREFERRED_WINDOW) {
            [$windowFrom, $windowTo] = $this->resolveWindowFromLegacySchedule($attributes);
        }

        $validUntil = $this->resolveValidUntil(
            serviceMode: $serviceMode,
            windowToAt: $windowTo,
            waitPreference: $waitPreference,
            now: $now,
        );

        return [
            'service_mode' => $serviceMode,
            'window_from_at' => $windowFrom,
            'window_to_at' => $windowTo,
            'valid_until_at' => $validUntil,
            'client_wait_preference' => $waitPreference,
            'promise_policy_version' => (string) config('order_promise.policy_version', 'v1'),
        ];
    }

    public function resolveExpiredReason(Order $order, ?Carbon $now = null): string
    {
        $now ??= now();

        if ($order->client_wait_preference === Order::WAIT_AUTO_CANCEL_IF_NOT_FOUND) {
            return Order::EXPIRED_REASON_CLIENT_AUTO_CANCEL_POLICY;
        }

        if ($order->service_mode === Order::SERVICE_MODE_PREFERRED_WINDOW && $order->window_to_at && $now->greaterThan($order->window_to_at)) {
            return Order::EXPIRED_REASON_COURIER_NOT_FOUND_WITHIN_WINDOW;
        }

        return Order::EXPIRED_REASON_COURIER_NOT_FOUND_WITHIN_VALIDITY;
    }

    private function inferServiceMode(array $attributes): string
    {
        if (! empty($attributes['window_from_at']) || ! empty($attributes['window_to_at'])) {
            return Order::SERVICE_MODE_PREFERRED_WINDOW;
        }

        if (! empty($attributes['scheduled_date']) && (! empty($attributes['time_from']) || ! empty($attributes['scheduled_time_from']))) {
            return Order::SERVICE_MODE_PREFERRED_WINDOW;
        }

        return Order::SERVICE_MODE_ASAP;
    }

    private function resolveWindowFromLegacySchedule(array $attributes): array
    {
        if (! empty($attributes['window_from_at']) && ! empty($attributes['window_to_at'])) {
            $from = Carbon::parse((string) $attributes['window_from_at']);
            $to = Carbon::parse((string) $attributes['window_to_at']);

            return [$from, $to->greaterThan($from) ? $to : $from->copy()->addHours(2)];
        }

        $scheduledDate = $attributes['scheduled_date'] ?? null;
        $fromTime = $attributes['time_from'] ?? $attributes['scheduled_time_from'] ?? null;
        $toTime = $attributes['time_to'] ?? $attributes['scheduled_time_to'] ?? null;

        if (! $scheduledDate || ! $fromTime) {
            return [null, null];
        }

        $from = Carbon::parse(sprintf('%s %s', (string) $scheduledDate, (string) $fromTime));
        $to = $toTime
            ? Carbon::parse(sprintf('%s %s', (string) $scheduledDate, (string) $toTime))
            : $from->copy()->addHours(2);

        if ($to->lessThanOrEqualTo($from)) {
            $to = $from->copy()->addHours(2);
        }

        return [$from, $to];
    }

    private function resolveValidUntil(string $serviceMode, ?Carbon $windowToAt, string $waitPreference, Carbon $now): Carbon
    {
        $asapHours = max(1, (int) config('order_promise.asap_validity_hours', 4));
        $windowGraceHours = max(0, (int) config('order_promise.preferred_window_grace_hours', 2));
        $lateExtraHours = max(1, (int) config('order_promise.allow_late_extra_hours', 6));

        if ($serviceMode === Order::SERVICE_MODE_PREFERRED_WINDOW && $windowToAt instanceof Carbon) {
            $validUntil = $windowToAt->copy()->addHours($windowGraceHours);

            if ($waitPreference === Order::WAIT_ALLOW_LATE_FULFILLMENT) {
                $validUntil = $validUntil->copy()->addHours($lateExtraHours);
            }

            return $validUntil;
        }

        $validUntil = $now->copy()->addHours($asapHours);

        if ($waitPreference === Order::WAIT_ALLOW_LATE_FULFILLMENT) {
            $validUntil = $validUntil->copy()->addHours($lateExtraHours);
        }

        return $validUntil;
    }
}
