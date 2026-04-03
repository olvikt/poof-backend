<?php

declare(strict_types=1);

namespace App\Livewire\Client;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;

class PaymentsPage extends Component
{
    public bool $embedded = false;

    public array $stats = [
        'total_spent' => 0,
        'month_spent' => 0,
        'paid_orders' => 0,
        'paid_subscriptions' => 0,
        'last_payment' => null,
    ];

    public Collection $operations;

    public function mount(bool $embedded = false): void
    {
        $this->embedded = $embedded;
        $userId = (int) auth()->id();
        $now = CarbonImmutable::now();

        $orders = Order::query()
            ->where('client_id', $userId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $paidOrders = $orders->where('payment_status', Order::PAY_PAID);

        $this->stats = [
            'total_spent' => $this->sumPaidAmount($paidOrders),
            'month_spent' => $this->sumPaidAmount(
                $paidOrders->filter(fn (Order $order): bool => $order->created_at?->between($now->startOfMonth(), $now->endOfMonth()) ?? false)
            ),
            'paid_orders' => $paidOrders->count(),
            'paid_subscriptions' => $paidOrders->whereNotNull('subscription_id')->count(),
            'last_payment' => $paidOrders->first(),
        ];

        $this->operations = $orders->map(function (Order $order): array {
            $type = 'Інше';

            if ($order->subscription_id !== null && $order->origin === Order::ORIGIN_SUBSCRIPTION) {
                $type = 'Продовження підписки';
            } elseif ($order->subscription_id !== null || $order->order_type === Order::TYPE_SUBSCRIPTION) {
                $type = 'Підписка';
            } elseif ($order->order_type === Order::TYPE_ONE_TIME) {
                $type = 'Разове замовлення';
            }

            return [
                'id' => (int) $order->id,
                'created_at' => $order->created_at,
                'amount' => (int) ($order->client_charge_amount > 0 ? $order->client_charge_amount : $order->price),
                'status' => (string) $order->payment_status,
                'status_label' => (string) (Order::PAYMENT_LABELS[$order->payment_status] ?? $order->payment_status),
                'type' => $type,
                'subscription_id' => $order->subscription_id,
            ];
        });
    }

    protected function sumPaidAmount(Collection $orders): int
    {
        return $orders->sum(function (Order $order): int {
            return $order->client_charge_amount > 0
                ? (int) $order->client_charge_amount
                : (int) $order->price;
        });
    }

    public function render()
    {
        $view = view('livewire.client.payments-page');

        return $this->embedded ? $view : $view->layout('layouts.client');
    }
}
