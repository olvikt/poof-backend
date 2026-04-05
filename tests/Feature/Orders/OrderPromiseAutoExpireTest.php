<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Dispatch\OfferDispatcher;
use App\Services\Orders\OrderAutoExpireService;
use Tests\Concerns\BuildsOrderRuntimeFixtures;
use Tests\TestCase;

class OrderPromiseAutoExpireTest extends TestCase
{
    use BuildsOrderRuntimeFixtures;

    public function test_expired_validity_order_is_not_dispatchable(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $courier = User::factory()->create([
            'role' => 'courier',
            'is_online' => true,
            'is_available' => true,
            'last_lat' => 50.45,
            'last_lng' => 30.52,
        ]);

        $expired = $this->createDispatchableSearchingPaidOrder($client, [
            'lat' => 50.45,
            'lng' => 30.52,
            'valid_until_at' => now()->subMinute(),
        ]);

        $valid = $this->createDispatchableSearchingPaidOrder($client, [
            'lat' => 50.4501,
            'lng' => 30.5201,
            'valid_until_at' => now()->addHours(1),
        ]);

        app(OfferDispatcher::class)->dispatchSearchingOrders(20);

        $this->assertDatabaseMissing('order_offers', ['order_id' => $expired->id, 'status' => OrderOffer::STATUS_PENDING]);
        $this->assertDatabaseHas('order_offers', ['order_id' => $valid->id, 'status' => OrderOffer::STATUS_PENDING]);
    }

    public function test_auto_expire_marks_searching_order_and_expires_live_offers(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $courier = User::factory()->create(['role' => 'courier']);

        $order = $this->createDispatchableSearchingPaidOrder($client, [
            'valid_until_at' => now()->subMinute(),
            'service_mode' => Order::SERVICE_MODE_PREFERRED_WINDOW,
            'window_to_at' => now()->subMinutes(10),
            'client_wait_preference' => Order::WAIT_AUTO_CANCEL_IF_NOT_FOUND,
        ]);

        OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->addMinute(),
        ]);

        $expiredCount = app(OrderAutoExpireService::class)->run();

        $this->assertSame(1, $expiredCount);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_CANCELLED,
            'expired_reason' => Order::EXPIRED_REASON_CLIENT_AUTO_CANCEL_POLICY,
        ]);
        $this->assertDatabaseHas('order_offers', [
            'order_id' => $order->id,
            'status' => OrderOffer::STATUS_EXPIRED,
        ]);
    }

    public function test_allow_late_fulfillment_extends_validity_not_forever(): void
    {
        config()->set('order_promise.allow_late_extra_hours', 3);

        $client = User::factory()->create(['role' => 'client']);

        $order = $this->createDispatchableSearchingPaidOrder($client, [
            'service_mode' => Order::SERVICE_MODE_ASAP,
            'client_wait_preference' => Order::WAIT_ALLOW_LATE_FULFILLMENT,
            'valid_until_at' => now()->subSecond(),
        ]);

        $expired = app(OrderAutoExpireService::class)->run();

        $this->assertSame(1, $expired);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_CANCELLED,
            'expired_reason' => Order::EXPIRED_REASON_COURIER_NOT_FOUND_WITHIN_VALIDITY,
        ]);
    }
}
