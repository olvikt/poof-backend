<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Models\Courier;
use App\Models\CourierOrderInterest;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
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
}
