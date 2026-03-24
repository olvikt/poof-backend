<?php

declare(strict_types=1);

namespace App\Actions\Orders\Lifecycle;

use App\Events\OrderCreated;
use App\Models\Order;

class MarkOrderAsPaidAction
{
    /**
     * Payment transition to canonical dispatchable state.
     */
    public function handle(Order $order): void
    {
        $order->forceFill([
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_SEARCHING,
        ])->save();

        event(new OrderCreated($order));
    }
}
