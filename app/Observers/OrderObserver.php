<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function created(Order $order): void
    {
        if (! $order->isSubscriptionExecution()) {
            return;
        }

        Log::info('execution_order_created', [
            'order_id' => (int) $order->id,
            'status' => (string) $order->status,
        ]);

        if ($order->status !== Order::STATUS_SEARCHING) {
            Log::warning('execution_order_status_unexpected_after_create', [
                'order_id' => (int) $order->id,
                'status' => (string) $order->status,
                'expected_status' => Order::STATUS_SEARCHING,
            ]);
        }
    }

    public function updated(Order $order): void
    {
        if (! $order->isSubscriptionExecution()) {
            return;
        }

        if ($order->status === Order::STATUS_SEARCHING) {
            return;
        }

        $ageSeconds = (int) $order->created_at?->diffInSeconds(now());

        if ($ageSeconds <= 2) {
            Log::warning('execution_order_status_changed_too_early', [
                'order_id' => (int) $order->id,
                'status' => (string) $order->status,
                'age_seconds' => $ageSeconds,
                'expected_status' => Order::STATUS_SEARCHING,
            ]);
        }
    }
}
