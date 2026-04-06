<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Dispatch\PendingOfferSweeper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingOfferSweeperTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_offer_expires_when_expires_at_in_past(): void
    {
        [$order, $courier] = $this->makeOrderAndCourier();

        $offer = OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->subSecond(),
        ]);

        $expired = app(PendingOfferSweeper::class)->run(100);

        $this->assertSame(1, $expired);
        $this->assertDatabaseHas('order_offers', [
            'id' => $offer->id,
            'status' => OrderOffer::STATUS_EXPIRED,
        ]);
    }

    public function test_alive_pending_offer_is_untouched(): void
    {
        [$order, $courier] = $this->makeOrderAndCourier();

        $offer = OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->addMinute(),
        ]);

        $expired = app(PendingOfferSweeper::class)->run(100);

        $this->assertSame(0, $expired);
        $this->assertDatabaseHas('order_offers', [
            'id' => $offer->id,
            'status' => OrderOffer::STATUS_PENDING,
        ]);
    }

    public function test_repeated_sweeper_run_is_idempotent(): void
    {
        [$order, $courier] = $this->makeOrderAndCourier();

        OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->subSecond(),
        ]);

        $first = app(PendingOfferSweeper::class)->run(100);
        $second = app(PendingOfferSweeper::class)->run(100);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
    }

    public function test_batch_limit_respected(): void
    {
        [$order, $courier] = $this->makeOrderAndCourier();

        for ($i = 0; $i < 3; $i++) {
            OrderOffer::query()->create([
                'order_id' => $order->id,
                'courier_id' => $courier->id,
                'type' => OrderOffer::TYPE_PRIMARY,
                'sequence' => 1,
                'status' => OrderOffer::STATUS_PENDING,
                'expires_at' => now()->subSecond(),
            ]);
        }

        $expired = app(PendingOfferSweeper::class)->run(2);

        $this->assertSame(2, $expired);
        $this->assertSame(1, OrderOffer::query()->where('status', OrderOffer::STATUS_PENDING)->count());
    }

    private function makeOrderAndCourier(): array
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'TTL sweeper order',
            'price' => 100,
            'lat' => 50.4501,
            'lng' => 30.5234,
        ]);

        return [$order, $courier];
    }
}
