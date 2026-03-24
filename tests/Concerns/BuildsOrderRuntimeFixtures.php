<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Order;
use App\Models\User;

trait BuildsOrderRuntimeFixtures
{
    protected function createDispatchableSearchingPaidOrder(User $client, array $overrides = []): Order
    {
        return Order::createForTesting(array_merge([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Тестова, 1',
            'price' => 100,
        ], $overrides));
    }

    protected function createAcceptedOrderAssignedToCourier(User $client, User $courier, array $overrides = []): Order
    {
        $order = Order::createForTesting(array_merge([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Активна, 11',
            'price' => 100,
            'accepted_at' => now(),
        ], $overrides));

        $courier->markBusy();
        $courier->refresh();

        return $order->fresh();
    }

    protected function createInProgressOrderAssignedToCourier(User $client, User $courier, array $overrides = []): Order
    {
        $order = Order::createForTesting(array_merge([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Активна, 11',
            'price' => 100,
            'accepted_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(5),
        ], $overrides));

        $courier->markDelivering();
        $courier->refresh();

        return $order->fresh();
    }
}
