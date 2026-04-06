<?php

declare(strict_types=1);

namespace App\Services\Dispatch;

use App\Models\Order;
use App\Models\OrderOffer;
use Illuminate\Support\Facades\Cache;

class DispatchTriggerPolicy
{
    public const SOURCE_ORDER_CREATED = 'order_created';
    public const SOURCE_ORDER_COMPLETED = 'order_completed';
    public const SOURCE_LOCATION_UPDATE = 'location_update';
    public const SOURCE_SCHEDULER = 'scheduler';

    public const SCOPE_SINGLE_ORDER = 'single_order';
    public const SCOPE_QUEUE_BATCH = 'queue_batch';

    /**
     * @param  array<string,mixed>  $context
     * @return array{allowed:bool,reason:string,coalescing_key:?string,dispatch_scope:string,throttle_window_ms:?int,order_id:?int,courier_id:?int}
     */
    public function decide(string $source, array $context = []): array
    {
        $order = $context['order'] ?? null;
        $courierId = isset($context['courier_id']) ? (int) $context['courier_id'] : null;

        $decision = [
            'allowed' => true,
            'reason' => 'policy_allowed',
            'coalescing_key' => null,
            'dispatch_scope' => $order instanceof Order ? self::SCOPE_SINGLE_ORDER : self::SCOPE_QUEUE_BATCH,
            'throttle_window_ms' => null,
            'order_id' => $order instanceof Order ? (int) $order->id : null,
            'courier_id' => $courierId,
        ];

        if ($order instanceof Order) {
            if (! $order->isDispatchableForOfferPipeline()) {
                return $this->skip($decision, 'order_not_dispatchable');
            }

            if ($order->next_dispatch_at?->isFuture()) {
                return $this->skip($decision, 'order_waiting_next_dispatch');
            }

            if ($this->hasLivePendingOffer((int) $order->id)) {
                return $this->skip($decision, 'live_pending_offer_exists');
            }

            if ($source !== self::SOURCE_ORDER_CREATED) {
                $windowMs = $this->orderCooldownMs($source);
                $coalescingKey = sprintf('dispatch-trigger:order:%d:%s', (int) $order->id, $source);

                if (! $this->acquireCoalescingSlot($coalescingKey, $windowMs)) {
                    return $this->skip($decision, 'order_trigger_coalesced', $coalescingKey, $windowMs);
                }

                $decision['coalescing_key'] = $coalescingKey;
                $decision['throttle_window_ms'] = $windowMs;
            }

            return $decision;
        }

        if ($source === self::SOURCE_LOCATION_UPDATE) {
            $decision['dispatch_scope'] = self::SCOPE_QUEUE_BATCH;

            if (! ((bool) ($context['online'] ?? false))) {
                return $this->skip($decision, 'courier_offline');
            }

            if (! ((bool) ($context['has_moved_enough'] ?? false))) {
                return $this->skip($decision, 'movement_below_threshold');
            }

            $windowMs = (int) config('dispatch.trigger.location_cooldown_ms', 5000);
            $coalescingKey = sprintf('dispatch-trigger:location:courier:%s', $courierId ?? 'unknown');

            if (! $this->acquireCoalescingSlot($coalescingKey, $windowMs)) {
                return $this->skip($decision, 'location_update_cooldown', $coalescingKey, $windowMs);
            }

            $decision['coalescing_key'] = $coalescingKey;
            $decision['throttle_window_ms'] = $windowMs;

            return $decision;
        }

        if ($source === self::SOURCE_SCHEDULER) {
            $windowMs = (int) config('dispatch.trigger.scheduler_cooldown_ms', 3000);
            $limit = max(1, (int) ($context['limit'] ?? 20));
            $coalescingKey = sprintf('dispatch-trigger:scheduler:limit:%d', $limit);

            if (! $this->acquireCoalescingSlot($coalescingKey, $windowMs)) {
                return $this->skip($decision, 'scheduler_queue_hot', $coalescingKey, $windowMs);
            }

            $decision['dispatch_scope'] = self::SCOPE_QUEUE_BATCH;
            $decision['coalescing_key'] = $coalescingKey;
            $decision['throttle_window_ms'] = $windowMs;

            return $decision;
        }

        if ($source === self::SOURCE_ORDER_COMPLETED) {
            $windowMs = (int) config('dispatch.trigger.order_completed_cooldown_ms', 1000);
            $coalescingKey = sprintf('dispatch-trigger:order-completed:courier:%s', $courierId ?? 'unknown');

            if (! $this->acquireCoalescingSlot($coalescingKey, $windowMs)) {
                return $this->skip($decision, 'order_completed_coalesced', $coalescingKey, $windowMs);
            }

            $decision['dispatch_scope'] = self::SCOPE_QUEUE_BATCH;
            $decision['coalescing_key'] = $coalescingKey;
            $decision['throttle_window_ms'] = $windowMs;
        }

        return $decision;
    }

    private function hasLivePendingOffer(int $orderId): bool
    {
        return OrderOffer::query()
            ->where('order_id', $orderId)
            ->where('status', OrderOffer::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->exists();
    }

    private function acquireCoalescingSlot(string $key, int $windowMs): bool
    {
        $ttlSeconds = max(1, (int) ceil($windowMs / 1000));

        return Cache::add($key, 1, now()->addSeconds($ttlSeconds));
    }

    private function orderCooldownMs(string $source): int
    {
        return match ($source) {
            self::SOURCE_ORDER_COMPLETED => (int) config('dispatch.trigger.order_completed_cooldown_ms', 1000),
            default => (int) config('dispatch.trigger.order_cooldown_ms', 1200),
        };
    }

    /**
     * @param  array{allowed:bool,reason:string,coalescing_key:?string,dispatch_scope:string,throttle_window_ms:?int,order_id:?int,courier_id:?int}  $decision
     * @return array{allowed:bool,reason:string,coalescing_key:?string,dispatch_scope:string,throttle_window_ms:?int,order_id:?int,courier_id:?int}
     */
    private function skip(array $decision, string $reason, ?string $key = null, ?int $windowMs = null): array
    {
        $decision['allowed'] = false;
        $decision['reason'] = $reason;
        $decision['coalescing_key'] = $key;
        $decision['throttle_window_ms'] = $windowMs;

        return $decision;
    }
}
