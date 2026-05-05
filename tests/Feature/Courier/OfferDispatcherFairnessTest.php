<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Dispatch\OfferDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferDispatcherFairnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_courier_does_not_receive_same_order_more_than_max_attempts(): void
    {
        config()->set('dispatch.fairness.max_offer_attempts_per_courier', 2);
        config()->set('dispatch.fairness.reoffer_cooldown_minutes', 0);

        $courier = $this->createCourier(50.4501, 30.5234);
        $order = $this->createSearchingOrder();

        OrderOffer::query()->create(['order_id' => $order->id, 'courier_id' => $courier->id, 'type' => OrderOffer::TYPE_PRIMARY, 'status' => OrderOffer::STATUS_DECLINED]);
        OrderOffer::query()->create(['order_id' => $order->id, 'courier_id' => $courier->id, 'type' => OrderOffer::TYPE_PRIMARY, 'status' => OrderOffer::STATUS_EXPIRED]);

        $offer = app(OfferDispatcher::class)->dispatchForOrder($order->fresh(), 'test_max_attempts');
        $this->assertNull($offer);
    }

    public function test_courier_can_receive_again_after_cooldown(): void
    {
        config()->set('dispatch.fairness.max_offer_attempts_per_courier', 3);
        config()->set('dispatch.fairness.reoffer_cooldown_minutes', 5);

        $courier = $this->createCourier(50.4501, 30.5234);
        $order = $this->createSearchingOrder();

        OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'status' => OrderOffer::STATUS_DECLINED,
            'updated_at' => now()->subMinutes(6),
            'created_at' => now()->subMinutes(6),
        ]);

        $offer = app(OfferDispatcher::class)->dispatchForOrder($order->fresh(), 'test_after_cooldown');
        $this->assertNotNull($offer);
        $this->assertSame($courier->id, $offer->courier_id);
    }

    public function test_rejected_courier_does_not_get_immediate_reoffer(): void
    {
        config()->set('dispatch.fairness.max_offer_attempts_per_courier', 3);
        config()->set('dispatch.fairness.reoffer_cooldown_minutes', 10);

        $courier = $this->createCourier(50.4501, 30.5234);
        $order = $this->createSearchingOrder();

        OrderOffer::query()->create(['order_id' => $order->id, 'courier_id' => $courier->id, 'type' => OrderOffer::TYPE_PRIMARY, 'status' => OrderOffer::STATUS_DECLINED]);

        $offer = app(OfferDispatcher::class)->dispatchForOrder($order->fresh(), 'test_cooldown_block');
        $this->assertNull($offer);
    }

    public function test_fairness_prefers_less_loaded_courier(): void
    {
        $busy = $this->createCourier(50.4501, 30.5234);
        $free = $this->createCourier(50.4502, 30.5235);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $order = $this->createSearchingOrder($client->id);

        Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $busy->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'completed_at' => now()->subHour(),
            'address_text' => 'done',
            'price' => 100,
        ]);

        $offer = app(OfferDispatcher::class)->dispatchForOrder($order->fresh(), 'test_fairness');
        $this->assertNotNull($offer);
        $this->assertSame($free->id, $offer->courier_id);
    }

    private function createCourier(float $lat, float $lng): User
    {
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true, 'is_online' => true, 'last_lat' => $lat, 'last_lng' => $lng, 'last_seen_at' => now()]);
        Courier::query()->create(['user_id' => $courier->id, 'status' => Courier::STATUS_ONLINE, 'last_location_at' => now()]);
        return $courier;
    }

    private function createSearchingOrder(?int $clientId = null): Order
    {
        $client = $clientId ? User::query()->findOrFail($clientId) : User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        return Order::createForTesting(['client_id' => $client->id, 'status' => Order::STATUS_SEARCHING, 'payment_status' => Order::PAY_PAID, 'address_text' => 'dispatch fairness order', 'price' => 190, 'lat' => 50.4502, 'lng' => 30.5235]);
    }
}
