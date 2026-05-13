<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Models\Courier;
use App\Models\CourierOrderInterest;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Http\Resources\CourierAvailableOfferResource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduledFinalMatchingCommandTest extends TestCase
{
    public function test_interested_online_courier_receives_offer_with_ttl(): void
    {
        CarbonImmutable::setTestNow('2026-05-12 10:00:00');
        config()->set('courier_runtime.scheduled_matching.offer_ttl_seconds', 45);

        $courier = User::factory()->verifiedCourier()->create(['is_online' => true, 'is_busy' => false]);
        Courier::query()->create(['user_id' => $courier->id, 'status' => Courier::STATUS_ONLINE, 'is_verified' => true, 'last_location_at' => now()]);

        $order = Order::createForTesting([
            'client_id' => User::factory()->create()->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'window_from_at' => now()->addMinutes(30),
        ]);

        CourierOrderInterest::query()->create(['order_id' => $order->id, 'courier_id' => $courier->id, 'status' => CourierOrderInterest::STATUS_INTERESTED, 'expressed_at' => now()]);

        Artisan::call('courier:finalize-scheduled-order-matching');

        $this->assertDatabaseHas('order_offers', ['order_id' => $order->id, 'courier_id' => $courier->id, 'status' => OrderOffer::STATUS_PENDING]);
        $this->assertNull($order->fresh()->courier_id);
        $offer = OrderOffer::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(45, now()->diffInSeconds($offer->expires_at));
    }

    public function test_duplicate_run_does_not_create_duplicate_offer(): void
    {
        CarbonImmutable::setTestNow('2026-05-12 10:00:00');
        $courier = User::factory()->verifiedCourier()->create(['is_online' => true]);
        Courier::query()->create(['user_id' => $courier->id, 'status' => Courier::STATUS_ONLINE, 'is_verified' => true, 'last_location_at' => now()]);
        $order = Order::createForTesting(['client_id' => User::factory()->create()->id, 'status' => Order::STATUS_SEARCHING, 'payment_status' => Order::PAY_PAID, 'window_from_at' => now()->addMinutes(30)]);
        CourierOrderInterest::query()->create(['order_id' => $order->id, 'courier_id' => $courier->id, 'status' => CourierOrderInterest::STATUS_INTERESTED, 'expressed_at' => now()]);

        Artisan::call('courier:finalize-scheduled-order-matching');
        Artisan::call('courier:finalize-scheduled-order-matching');

        $this->assertSame(1, OrderOffer::query()->where('order_id', $order->id)->count());
    }

    public function test_expired_offer_is_intentionally_retried_on_next_scheduler_run(): void
    {
        CarbonImmutable::setTestNow('2026-05-12 10:00:00');
        config()->set('courier_runtime.scheduled_matching.offer_ttl_seconds', 45);

        $courier = User::factory()->verifiedCourier()->create(['is_online' => true, 'is_busy' => false]);
        Courier::query()->create(['user_id' => $courier->id, 'status' => Courier::STATUS_ONLINE, 'is_verified' => true, 'last_location_at' => now()]);
        $order = Order::createForTesting(['client_id' => User::factory()->create()->id, 'status' => Order::STATUS_SEARCHING, 'payment_status' => Order::PAY_PAID, 'window_from_at' => now()->addMinutes(29)]);
        CourierOrderInterest::query()->create(['order_id' => $order->id, 'courier_id' => $courier->id, 'status' => CourierOrderInterest::STATUS_INTERESTED, 'expressed_at' => now()]);

        Artisan::call('courier:finalize-scheduled-order-matching');
        CarbonImmutable::setTestNow(now()->addMinutes(1));
        Artisan::call('courier:sweep-pending-offers');
        Artisan::call('courier:finalize-scheduled-order-matching');

        $this->assertSame(1, OrderOffer::query()->where('order_id', $order->id)->count());
        $this->assertSame(0, OrderOffer::query()->where('order_id', $order->id)->where('status', OrderOffer::STATUS_PENDING)->count());
    }

    public function test_expired_offer_is_retried_after_cooldown_to_next_eligible_interested_courier(): void
    {
        CarbonImmutable::setTestNow('2026-05-12 10:00:00');
        config()->set('courier_runtime.scheduled_matching.offer_ttl_seconds', 45);
        config()->set('courier_runtime.scheduled_matching.courier_reoffer_cooldown_seconds', 120);

        $first = User::factory()->verifiedCourier()->create(['is_online' => true, 'is_busy' => false]);
        $second = User::factory()->verifiedCourier()->create(['is_online' => true, 'is_busy' => false]);
        Courier::query()->create(['user_id' => $first->id, 'status' => Courier::STATUS_ONLINE, 'is_verified' => true, 'last_location_at' => now(), 'rating' => 4.8]);
        Courier::query()->create(['user_id' => $second->id, 'status' => Courier::STATUS_ONLINE, 'is_verified' => true, 'last_location_at' => now(), 'rating' => 4.8]);
        $order = Order::createForTesting(['client_id' => User::factory()->create()->id, 'status' => Order::STATUS_SEARCHING, 'payment_status' => Order::PAY_PAID, 'window_from_at' => now()->addMinutes(29)]);
        CourierOrderInterest::query()->create(['order_id' => $order->id, 'courier_id' => $first->id, 'status' => CourierOrderInterest::STATUS_INTERESTED, 'expressed_at' => now()]);
        CourierOrderInterest::query()->create(['order_id' => $order->id, 'courier_id' => $second->id, 'status' => CourierOrderInterest::STATUS_INTERESTED, 'expressed_at' => now()->addSecond()]);

        Artisan::call('courier:finalize-scheduled-order-matching');
        CarbonImmutable::setTestNow(now()->addMinutes(3));
        Artisan::call('courier:sweep-pending-offers');
        Artisan::call('courier:finalize-scheduled-order-matching');

        $this->assertDatabaseHas('order_offers', ['order_id' => $order->id, 'courier_id' => $first->id, 'status' => OrderOffer::STATUS_EXPIRED]);
        $this->assertDatabaseHas('order_offers', ['order_id' => $order->id, 'courier_id' => $second->id, 'status' => OrderOffer::STATUS_PENDING]);
    }

    public function test_seconds_remaining_is_never_negative(): void
    {
        CarbonImmutable::setTestNow('2026-05-12 10:10:00');
        $offer = new OrderOffer(['status' => OrderOffer::STATUS_PENDING, 'expires_at' => now()->subSeconds(5)]);
        $offer->id = 1;

        $payload = (new CourierAvailableOfferResource($offer))->toArray(request());
        $this->assertSame(0, $payload['seconds_remaining']);
    }

    public function test_lead_window_uses_app_timezone_consistently(): void
    {
        config()->set('app.timezone', 'Europe/Kyiv');
        date_default_timezone_set('Europe/Kyiv');
        CarbonImmutable::setTestNow('2026-05-12 10:00:00');

        $courier = User::factory()->verifiedCourier()->create(['is_online' => true, 'is_busy' => false]);
        Courier::query()->create(['user_id' => $courier->id, 'status' => Courier::STATUS_ONLINE, 'is_verified' => true, 'last_location_at' => now()]);
        $order = Order::createForTesting(['client_id' => User::factory()->create()->id, 'status' => Order::STATUS_SEARCHING, 'payment_status' => Order::PAY_PAID, 'window_from_at' => now()->addMinutes(30)]);
        CourierOrderInterest::query()->create(['order_id' => $order->id, 'courier_id' => $courier->id, 'status' => CourierOrderInterest::STATUS_INTERESTED, 'expressed_at' => now()]);

        Artisan::call('courier:finalize-scheduled-order-matching');

        $this->assertDatabaseHas('order_offers', ['order_id' => $order->id, 'courier_id' => $courier->id]);
    }

    public function test_unreliable_interested_courier_is_skipped_by_rating_guard(): void
    {
        CarbonImmutable::setTestNow('2026-05-12 10:00:00');
        config()->set('courier_runtime.scheduled_matching.min_reliable_rating', 4.5);

        $courier = User::factory()->verifiedCourier()->create(['is_online' => true, 'is_busy' => false]);
        Courier::query()->create(['user_id' => $courier->id, 'status' => Courier::STATUS_ONLINE, 'is_verified' => true, 'last_location_at' => now(), 'rating' => 3.1]);
        $order = Order::createForTesting(['client_id' => User::factory()->create()->id, 'status' => Order::STATUS_SEARCHING, 'payment_status' => Order::PAY_PAID, 'window_from_at' => now()->addMinutes(30)]);
        CourierOrderInterest::query()->create(['order_id' => $order->id, 'courier_id' => $courier->id, 'status' => CourierOrderInterest::STATUS_INTERESTED, 'expressed_at' => now()]);

        Artisan::call('courier:finalize-scheduled-order-matching');

        $this->assertDatabaseMissing('order_offers', ['order_id' => $order->id, 'courier_id' => $courier->id]);
    }
}
