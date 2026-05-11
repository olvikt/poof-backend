<?php

declare(strict_types=1);

namespace App\Support\Orders;

use App\Models\Order;

class OrderLifecycleTransitionPolicy
{
    public const FLOW_DEFAULT = 'default';
    public const FLOW_ADMIN_OVERRIDE = 'admin_override';
    public const FLOW_SUBSCRIPTION_CHECKOUT = 'subscription_checkout';

    /** @var array<string, array<int, string>> */
    private const BASE = [
        Order::STATUS_NEW => [Order::STATUS_SEARCHING, Order::STATUS_CANCELLED],
        Order::STATUS_SEARCHING => [Order::STATUS_ACCEPTED, Order::STATUS_EXPIRED, Order::STATUS_CANCELLED],
        Order::STATUS_ACCEPTED => [Order::STATUS_IN_PROGRESS],
        Order::STATUS_IN_PROGRESS => [Order::STATUS_DONE],
        Order::STATUS_DONE => [],
        Order::STATUS_CANCELLED => [],
        Order::STATUS_EXPIRED => [],
    ];

    public function canTransition(string $from, string $to, string $flow = self::FLOW_DEFAULT): bool
    {
        if ($from === $to) {
            return true;
        }

        if ($flow === self::FLOW_ADMIN_OVERRIDE && $from === Order::STATUS_ACCEPTED && $to === Order::STATUS_CANCELLED) {
            return true;
        }

        if ($flow === self::FLOW_SUBSCRIPTION_CHECKOUT && $from === Order::STATUS_NEW && $to === Order::STATUS_DONE) {
            return true;
        }

        return in_array($to, self::BASE[$from] ?? [], true);
    }

    public function assertTransition(string $from, string $to, string $flow = self::FLOW_DEFAULT): void
    {
        if (! $this->canTransition($from, $to, $flow)) {
            throw new \DomainException(sprintf('Forbidden order lifecycle transition: %s -> %s (flow=%s)', $from, $to, $flow));
        }
    }
}
