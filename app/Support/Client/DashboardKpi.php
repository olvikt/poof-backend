<?php

declare(strict_types=1);

namespace App\Support\Client;

use App\Models\ClientSubscription;
use App\Models\Order;
use Carbon\CarbonImmutable;

class DashboardKpi
{
    public function activeSubscriptionsCount(int $clientId): int
    {
        return ClientSubscription::query()
            ->where('client_id', $clientId)
            ->withCount([
                'generatedOrders as paid_orders_count' => fn ($query) => $query->where('payment_status', Order::PAY_PAID),
            ])
            ->get()
            ->filter(fn (ClientSubscription $subscription): bool => $subscription->display_status === ClientSubscription::STATUS_ACTIVE)
            ->count();
    }

    public function totalPaidAmount(int $clientId): int
    {
        return (int) Order::query()
            ->where('client_id', $clientId)
            ->where('payment_status', Order::PAY_PAID)
            ->get()
            ->sum(fn (Order $order): int => $order->client_charge_amount > 0
                ? (int) $order->client_charge_amount
                : (int) $order->price);
    }

    public function monthPaidAmount(int $clientId, CarbonImmutable $now): int
    {
        return (int) Order::query()
            ->where('client_id', $clientId)
            ->where('payment_status', Order::PAY_PAID)
            ->whereBetween('created_at', [$now->startOfMonth(), $now->endOfMonth()])
            ->get()
            ->sum(fn (Order $order): int => $order->client_charge_amount > 0
                ? (int) $order->client_charge_amount
                : (int) $order->price);
    }
}
