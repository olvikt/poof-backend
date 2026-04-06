<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SearchingOrderDiagnosticsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_with_sane_next_dispatch_at_not_flagged(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'sane',
            'price' => 100,
        ])->forceFill(['next_dispatch_at' => now()->addMinute()])->save();

        Artisan::call('courier:diagnose-searching-orders', ['--limit' => 20]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([], $payload['stuck']);
    }

    public function test_order_with_far_future_next_dispatch_at_is_flagged(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'far future',
            'price' => 100,
        ]);
        $order->forceFill(['next_dispatch_at' => now()->addMinutes(30)])->save();

        Artisan::call('courier:diagnose-searching-orders', ['--limit' => 20]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($order->id, $payload['stuck'][0]['order_id']);
        $this->assertSame('next_dispatch_far_future', $payload['stuck'][0]['anomaly_reason']);
    }

    public function test_overdue_searching_order_is_flagged(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'old searching',
            'price' => 100,
        ]);
        $order->forceFill(['created_at' => now()->subHours(2)])->save();

        Artisan::call('courier:diagnose-searching-orders', ['--limit' => 20]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($order->id, $payload['stuck'][0]['order_id']);
        $this->assertSame('searching_age_overdue', $payload['stuck'][0]['anomaly_reason']);
    }

    public function test_invalid_or_expired_order_is_classified_and_not_flagged_as_stuck(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'expired',
            'price' => 100,
            'expired_at' => now()->subMinute(),
        ]);
        $order->forceFill(['next_dispatch_at' => now()->addHours(2)])->save();

        Artisan::call('courier:diagnose-searching-orders', ['--limit' => 20]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([], $payload['stuck']);
        $this->assertSame($order->id, $payload['classified'][0]['order_id']);
        $this->assertSame('invalid_or_expired', $payload['classified'][0]['classification']);
    }
}
