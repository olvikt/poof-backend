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
            ->select(['id', 'public_id', 'subscription_id', 'origin', 'service_mode', 'window_from_at', 'window_to_at', 'scheduled_date', 'address_text'])
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
                'order_public_id' => $order->public_id,
                'subscription_id' => $order->subscription_id,
                'order_type' => $order->subscription_id ? 'subscription' : 'one_time',
                'origin' => $order->origin,
                'title' => $order->subscription_id
                    ? sprintf('Винос по підписці #%d', $order->id)
                    : sprintf('Разовий винос #%d', $order->id),
                'subtitle' => $this->buildSubtitle($order),
                'target_url' => $order->subscription_id
                    ? route('client.subscriptions', [
                        'highlight' => 'awaiting-confirmation',
                        'subscription' => $order->subscription_id,
                        'order' => $order->id,
                    ])
                    : route('client.orders', ['highlight' => $order->id]),
                'target_label' => $order->subscription_id ? 'Відкрити підписку' : 'Перейти до замовлення',
                'submitted_at' => optional($completionRequest?->submitted_at)?->toIso8601String(),
                'auto_confirmation_due_at' => optional($completionRequest?->auto_confirmation_due_at)?->toIso8601String(),
            ];
        })->values()->all();

        return [
            'count' => count($items),
            'items' => $items,
        ];
    }

    private function buildSubtitle(Order $order): string
    {
        $parts = [];

        if ($order->service_mode === Order::SERVICE_MODE_ASAP) {
            $parts[] = 'Якнайшвидше';
        } elseif ($order->window_from_at && $order->window_to_at) {
            $parts[] = sprintf('%s–%s', $order->window_from_at->format('d.m H:i'), $order->window_to_at->format('d.m H:i'));
        } elseif ($order->scheduled_date) {
            $parts[] = $order->scheduled_date->format('d.m.Y');
        }

        if (! empty($order->address_text)) {
            $parts[] = $order->address_text;
        }

        return implode(' · ', $parts);
    }
}
