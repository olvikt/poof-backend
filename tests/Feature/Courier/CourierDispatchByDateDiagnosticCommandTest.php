<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CourierDispatchByDateDiagnosticCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_dispatch_window_for_deferred_subscription_order(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'scheduled_date' => '2026-05-11',
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'subscription_id' => 77,
            'dispatch_available_at' => now()->addMinutes(15),
        ]);

        Artisan::call('poof:diagnose-courier-dispatch', ['--date' => '2026-05-11', '--courier-id' => 2]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $row = collect($payload['orders'])->firstWhere('id', $order->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['is_dispatch_deferred']);
        $this->assertContains('dispatch_deferred_future_window', $row['reasons']);
        $this->assertSame($row['dispatch_available_at'], $row['dispatch_window_opens_at']);
        $this->assertSame(1, $payload['summary']['deferred']);
    }

    public function test_it_reports_bug_needs_dispatch_when_dispatchable_without_alive_offer(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'scheduled_date' => '2026-05-11',
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'subscription_id' => 88,
            'dispatch_available_at' => now()->subMinute(),
        ]);

        Artisan::call('poof:diagnose-courier-dispatch', ['--date' => '2026-05-11', '--courier-id' => 2]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $row = collect($payload['orders'])->firstWhere('id', $order->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['is_dispatchable_for_offer_pipeline']);
        $this->assertSame(0, $row['alive_pending_offers_count']);
        $this->assertContains('no_alive_pending_offer', $row['reasons']);
        $this->assertSame(1, $payload['summary']['dispatchable']);
        $this->assertSame(1, $payload['summary']['stuck_without_offers']);
    }

    public function test_it_reports_alive_offer_presence_and_courier_offer_flag(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'scheduled_date' => '2026-05-11',
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'dispatch_available_at' => now()->subMinute(),
        ]);

        OrderOffer::createPrimaryPending($order->id, 2, 120);

        Artisan::call('poof:diagnose-courier-dispatch', ['--date' => '2026-05-11', '--courier-id' => 2]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $row = collect($payload['orders'])->firstWhere('id', $order->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['alive_pending_offers_count']);
        $this->assertTrue($row['has_offer_for_courier_id']);
        $this->assertTrue($row['has_alive_pending_offer_for_courier_id']);
        $this->assertContains('no_offer_for_courier', $row['reasons']);
        $this->assertArrayHasKey('timezone', $payload);
        $this->assertArrayHasKey('queue', $payload);
    }
}
