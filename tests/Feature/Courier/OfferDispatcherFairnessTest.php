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

    public function test_max_attempts_blocks_courier(): void
    {
        config()->set('dispatch.fairness.max_offer_attempts_per_courier', 2);
        config()->set('dispatch.fairness.reoffer_cooldown_minutes', 0);

        $courier = $this->createCourier(50.4501, 30.5234);
        $order = $this->createSearchingOrder();

        $first = OrderOffer::createPrimaryPending($order->id, $courier->id, 30);
        $first->markDeclined();
        $second = OrderOffer::createPrimaryPending($order->id, $courier->id, 30);
        $second->markExpired();

        $offer = app(OfferDispatcher::class)->dispatchForOrder($order->fresh(), 'test_max_attempts');
        $this->assertNull($offer);
    }

    public function test_cooldown_uses_last_offered_at(): void
    {
        config()->set('dispatch.fairness.max_offer_attempts_per_courier', 3);
        config()->set('dispatch.fairness.reoffer_cooldown_minutes', 5);

        $courier = $this->createCourier(50.4501, 30.5234);
        $order = $this->createSearchingOrder();

        $offer = OrderOffer::createPrimaryPending($order->id, $courier->id, 30);
        $offer->markDeclined();
        $offer->forceFill(['last_offered_at' => now()->subMinutes(2)])->save();

        $next = app(OfferDispatcher::class)->dispatchForOrder($order->fresh(), 'test_last_offered_cooldown');
        $this->assertNull($next);
    }

    public function test_updated_at_change_does_not_extend_cooldown_when_last_offered_old(): void
    {
        config()->set('dispatch.fairness.max_offer_attempts_per_courier', 3);
        config()->set('dispatch.fairness.reoffer_cooldown_minutes', 5);

        $courier = $this->createCourier(50.4501, 30.5234);
        $order = $this->createSearchingOrder();

        $offer = OrderOffer::createPrimaryPending($order->id, $courier->id, 30);
        $offer->markDeclined();
        $offer->forceFill([
            'last_offered_at' => now()->subMinutes(6),
            'updated_at' => now(),
        ])->save();

        $next = app(OfferDispatcher::class)->dispatchForOrder($order->fresh(), 'test_updated_at_irrelevant');
        $this->assertNotNull($next);
        $this->assertSame($courier->id, $next->courier_id);
    }

    public function test_courier_is_eligible_after_cooldown(): void
    {
        config()->set('dispatch.fairness.max_offer_attempts_per_courier', 3);
        config()->set('dispatch.fairness.reoffer_cooldown_minutes', 5);

        $courier = $this->createCourier(50.4501, 30.5234);
        $order = $this->createSearchingOrder();

        $offer = OrderOffer::createPrimaryPending($order->id, $courier->id, 30);
        $offer->markExpired();
        $offer->forceFill(['last_offered_at' => now()->subMinutes(6)])->save();

        $next = app(OfferDispatcher::class)->dispatchForOrder($order->fresh(), 'test_after_cooldown');
        $this->assertNotNull($next);
        $this->assertSame($courier->id, $next->courier_id);
    }

    public function test_starvation_radius_expands_in_same_dispatch_cycle(): void
    {
        config()->set('dispatch.fairness.starvation_step_seconds', 15);
        config()->set('dispatch.fairness.starvation_radius_step_km', 2.0);
        config()->set('dispatch.fairness.starvation_max_extra_radius_km', 10.0);

        $courier = $this->createCourier(50.5320, 30.5234); // ~9km north
        $order = $this->createSearchingOrder();
        $order->forceFill(['dispatch_attempts' => 3])->save(); // after increment becomes 4 => expanded radius

        $next = app(OfferDispatcher::class)->dispatchForOrder($order->fresh(), 'test_starvation_same_cycle');
        $this->assertNotNull($next);
        $this->assertSame($courier->id, $next->courier_id);
    }

    private function createCourier(float $lat, float $lng): User
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'last_lat' => $lat,
            'last_lng' => $lng,
            'last_seen_at' => now(),
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
            'last_location_at' => now(),
        ]);

        return $courier;
    }

    private function createSearchingOrder(): Order
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        return Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'dispatch fairness order',
            'price' => 190,
            'lat' => 50.4502,
            'lng' => 30.5235,
        ]);
    }
}
