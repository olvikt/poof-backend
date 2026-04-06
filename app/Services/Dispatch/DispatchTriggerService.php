<?php

declare(strict_types=1);

namespace App\Services\Dispatch;

use App\Models\Order;
use App\Models\OrderOffer;
use Illuminate\Support\Facades\Log;

class DispatchTriggerService
{
    public function __construct(
        private readonly OfferDispatcher $dispatcher,
        private readonly DispatchTriggerPolicy $policy,
    ) {
    }

    public function triggerForOrder(Order $order, string $source): ?OrderOffer
    {
        $decision = $this->policy->decide($source, [
            'order' => $order,
        ]);

        $this->logTriggerBoundary('dispatch_trigger_received', $source, $decision);

        if (! $decision['allowed']) {
            $this->logTriggerBoundary('dispatch_trigger_skipped', $source, $decision, 'dispatch_trigger_skipped_total');

            return null;
        }

        $this->logTriggerBoundary('dispatch_trigger_allowed', $source, $decision, 'dispatch_trigger_allowed_total');

        return $this->dispatcher->dispatchForOrder($order, $source);
    }

    /**
     * @param  array<string,mixed>  $context
     */
    public function triggerQueueBatch(string $source, int $limit = 20, array $context = []): int
    {
        $context['limit'] = max(1, $limit);

        $decision = $this->policy->decide($source, $context);

        $this->logTriggerBoundary('dispatch_trigger_received', $source, $decision);

        if (! $decision['allowed']) {
            $this->logTriggerBoundary('dispatch_trigger_skipped', $source, $decision, 'dispatch_trigger_skipped_total');

            return 0;
        }

        $this->logTriggerBoundary('dispatch_trigger_allowed', $source, $decision, 'dispatch_trigger_allowed_total');

        return $this->dispatcher->dispatchSearchingOrders($limit);
    }

    /**
     * @param  array{allowed:bool,reason:string,coalescing_key:?string,dispatch_scope:string,throttle_window_ms:?int,order_id:?int,courier_id:?int}  $decision
     */
    private function logTriggerBoundary(string $message, string $source, array $decision, ?string $counter = null): void
    {
        $context = [
            'trigger_source' => $source,
            'reason' => $decision['reason'],
            'order_id' => $decision['order_id'],
            'courier_id' => $decision['courier_id'],
            'coalescing_key' => $decision['coalescing_key'],
            'dispatch_scope' => $decision['dispatch_scope'],
            'throttle_window_ms' => $decision['throttle_window_ms'],
            'allowed' => $decision['allowed'],
        ];

        if ($counter !== null) {
            $context['counter'] = $counter;
            $context['counter_increment'] = 1;
            $context['counter_labels'] = [
                'trigger_source' => $source,
                'reason' => $decision['reason'],
            ];
        }

        Log::info($message, $context);
    }
}
