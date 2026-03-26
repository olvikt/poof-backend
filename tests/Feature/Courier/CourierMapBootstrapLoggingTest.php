<?php

namespace Tests\Feature\Courier;

use App\Livewire\Courier\MyOrders;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

class CourierMapBootstrapLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_log_map_bootstrap_debug_context_when_debug_flag_is_disabled(): void
    {
        config()->set('dispatch.courier_map_bootstrap_debug', false);

        [$courier] = $this->createCourierWithActiveOrder();

        Log::spy();

        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)->assertOk();

        Log::shouldNotHaveReceived('debug');
    }

    public function test_it_logs_only_minimal_map_bootstrap_context_when_debug_flag_is_enabled(): void
    {
        config()->set('dispatch.courier_map_bootstrap_debug', true);

        [$courier, $order] = $this->createCourierWithActiveOrder();

        Log::spy();

        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)->assertOk();

        Log::shouldHaveReceived('debug')
            ->once()
            ->withArgs(function (string $message, array $context) use ($courier, $order): bool {
                $this->assertSame('courier map bootstrap prepared', $message);
                $this->assertSame($courier->id, $context['courier_id'] ?? null);
                $this->assertSame($order->id, $context['order_id'] ?? null);
                $this->assertArrayHasKey('has_courier_coordinates', $context);
                $this->assertArrayHasKey('courier_confirmed', $context);
                $this->assertArrayNotHasKey('payload', $context);

                return true;
            });
    }

    private function createCourierWithActiveOrder(): array
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => false,
            'is_online' => true,
            'last_lat' => 48.4647,
            'last_lng' => 35.0462,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
            'last_location_at' => now(),
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Логістична, 7',
            'price' => 125,
            'accepted_at' => now(),
            'lat' => 48.4660,
            'lng' => 35.0500,
        ]);

        return [$courier, $order];
    }
}
