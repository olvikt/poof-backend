<?php

declare(strict_types=1);

namespace App\Actions\Orders\Completion;

use App\Models\Order;
use App\Models\OrderCompletionRequest;
use App\Models\User;

class GetPendingConfirmationsForClientAction
{
    public function handle(User $client): array
    {
        $orders = Order::query()
            ->select(['id', 'subscription_id'])
            ->where('client_id', $client->id)
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_EXPIRED])
            ->whereHas('completionRequest', function ($query): void {
                $query->where('status', OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION);
            })
            ->with(['completionRequest:id,order_id,submitted_at,auto_confirmation_due_at,status'])
            ->orderByDesc('id')
            ->get();

        $items = $orders->map(function (Order $order): array {
            $completionRequest = $order->completionRequest;

            return [
                'order_id' => $order->id,
                'subscription_id' => $order->subscription_id,
                'submitted_at' => optional($completionRequest?->submitted_at)?->toIso8601String(),
                'auto_confirmation_due_at' => optional($completionRequest?->auto_confirmation_due_at)?->toIso8601String(),
            ];
        })->values()->all();

        return [
            'count' => count($items),
            'items' => $items,
        ];
    }
}
