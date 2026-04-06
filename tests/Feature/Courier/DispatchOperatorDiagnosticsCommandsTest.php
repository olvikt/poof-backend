<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DispatchOperatorDiagnosticsCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_returns_expected_verdict_fields_for_order(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'diag order',
            'price' => 100,
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        OrderOffer::createPrimaryPending($order->id, $courier->id, 120);

        Artisan::call('courier:why-order-not-dispatched', ['orderId' => $order->id]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('dispatch_eligibility', $payload);
        $this->assertArrayHasKey('live_pending_offer_count', $payload);
        $this->assertArrayHasKey('dispatch_attempts', $payload);
        $this->assertArrayHasKey('next_dispatch_at', $payload);
        $this->assertArrayHasKey('valid_until_at', $payload);
        $this->assertArrayHasKey('recent_exclusion_breakdown', $payload);
        $this->assertArrayHasKey('candidate_scan_summary', $payload);
    }

    public function test_eligible_courier_reported_as_eligible(): void
    {
        [$order, $courier] = $this->makeEligibleOrderAndCourier();

        Artisan::call('courier:why-courier-not-candidate', [
            'orderId' => $order->id,
            'courierId' => $courier->id,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('eligible', $payload['verdict']);
        $this->assertSame([], $payload['failed_rules']);
    }

    public function test_offline_stale_busy_courier_reported_with_exact_failing_rules(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'diag order',
            'price' => 100,
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'last_lat' => 50.45,
            'last_lng' => 30.52,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_OFFLINE,
            'last_location_at' => now()->subMinutes(10),
        ]);

        Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'busy order',
            'price' => 100,
        ]);

        Artisan::call('courier:why-courier-not-candidate', [
            'orderId' => $order->id,
            'courierId' => $courier->id,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('not_eligible', $payload['verdict']);
        $this->assertContains('courier_status_online', $payload['failed_rules']);
        $this->assertContains('location_fresh', $payload['failed_rules']);
        $this->assertContains('active_order_conflict', $payload['failed_rules']);
    }

    public function test_invalid_or_expired_order_clearly_classified_in_order_diagnostic(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_CANCELLED,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'invalid order',
            'price' => 100,
            'expired_at' => now()->subMinute(),
            'expired_reason' => Order::EXPIRED_REASON_COURIER_NOT_FOUND_WITHIN_VALIDITY,
        ]);

        Artisan::call('courier:why-order-not-dispatched', ['orderId' => $order->id]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('not_eligible', $payload['dispatch_eligibility']);
        $this->assertTrue($payload['is_expired']);
        $this->assertContains('status_not_searching', $payload['eligibility_reasons']);
    }

    private function makeEligibleOrderAndCourier(): array
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'eligible',
            'price' => 100,
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'last_lat' => 50.451,
            'last_lng' => 30.521,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
            'last_location_at' => now(),
        ]);

        return [$order, $courier];
    }
}
