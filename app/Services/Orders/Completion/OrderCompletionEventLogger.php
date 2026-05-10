<?php

declare(strict_types=1);

namespace App\Services\Orders\Completion;

use App\Models\OrderCompletionEvent;

class OrderCompletionEventLogger
{
    public function log(string $eventType, int $orderId, int $completionRequestId, string $actorType, ?int $actorId, string $fromStatus, string $toStatus, array $metadata = []): void
    {
        OrderCompletionEvent::unguarded(fn () => OrderCompletionEvent::query()->create([
            'event_type' => $eventType,
            'order_id' => $orderId,
            'completion_request_id' => $completionRequestId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => $metadata,
        ]));
    }
}
