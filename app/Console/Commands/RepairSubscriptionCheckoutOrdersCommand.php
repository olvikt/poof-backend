<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Subscriptions\CreateSubscriptionExecutionOrderAction;
use App\Models\ClientSubscription;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RepairSubscriptionCheckoutOrdersCommand extends Command
{
    protected $signature = 'subscriptions:repair-checkout-orders {--limit=200}';

    protected $description = 'Repair paid/searching subscription checkout orders by closing payment order and creating missing execution order';

    public function __construct(private readonly CreateSubscriptionExecutionOrderAction $createSubscriptionExecutionOrder)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $orders = Order::query()
            ->where('origin', Order::ORIGIN_CHECKOUT)
            ->where('order_type', Order::TYPE_SUBSCRIPTION)
            ->where('payment_status', Order::PAY_PAID)
            ->where('status', Order::STATUS_SEARCHING)
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        foreach ($orders as $order) {
            $order->forceFill(['status' => Order::STATUS_DONE])->save();

            if ($order->subscription_id === null) {
                continue;
            }

            $subscription = ClientSubscription::query()->with(['plan', 'address'])->find($order->subscription_id);
            if (! $subscription) {
                continue;
            }

            $this->createSubscriptionExecutionOrder->handle($subscription, CarbonImmutable::instance($order->created_at ?? now()));
        }

        $this->info('Repaired: '.$orders->count());

        return self::SUCCESS;
    }
}
