<?php

namespace Tests\Feature\Courier;

use App\Livewire\Courier\MyOrders;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierOrderExecutionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_order_updates_runtime_and_shows_success_feedback(): void
    {
        [$courier, $order] = $this->createCourierWithAssignedOrder();

        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->call('start', $order->id)
            ->assertDispatched('notify', type: 'success', message: 'Виконання розпочато')
            ->assertDispatched('courier-proof:reveal', orderId: $order->id)
            ->assertDispatched('$refresh');

        $order->refresh();
        $courier->refresh();

        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->status);
        $this->assertNotNull($order->started_at);
        $this->assertTrue((bool) $courier->is_busy);
    }

    public function test_start_legacy_order_does_not_emit_proof_reveal_signal(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'is_busy' => true,
            'session_state' => User::SESSION_ASSIGNED,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ASSIGNED,
            'last_location_at' => now(),
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Легасі, 2',
            'price' => 100,
            'accepted_at' => now(),
            'handover_type' => Order::HANDOVER_DOOR,
            'completion_policy' => Order::COMPLETION_POLICY_NONE,
        ]);

        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->call('start', $order->id)
            ->assertNotDispatched('courier-proof:reveal');
    }

    public function test_complete_order_updates_runtime_and_shows_success_feedback(): void
    {
        [$courier, $order] = $this->createCourierWithInProgressOrder();

        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->call('complete', $order->id)
            ->assertDispatched('notify', type: 'success', message: 'Замовлення виконано')
            ->assertDispatched('$refresh');

        $order->refresh();
        $courier->refresh();

        $this->assertSame(Order::STATUS_DONE, $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertFalse((bool) $courier->is_busy);
        $this->assertTrue((bool) $courier->is_online);
    }

    private function createCourierWithAssignedOrder(): array
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'is_busy' => true,
            'session_state' => User::SESSION_ASSIGNED,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ASSIGNED,
            'last_location_at' => now(),
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Курʼєрська, 1',
            'price' => 100,
            'accepted_at' => now(),
            'handover_type' => Order::HANDOVER_DOOR,
            'completion_policy' => Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM,
        ]);

        return [$courier, $order];
    }

    private function createCourierWithInProgressOrder(): array
    {
        [$courier, $order] = $this->createCourierWithAssignedOrder();

        $order->update([
            'status' => Order::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $courier->courierProfile()->update(['status' => Courier::STATUS_DELIVERING]);
        $courier->update([
            'is_online' => true,
            'is_busy' => true,
            'session_state' => User::SESSION_IN_PROGRESS,
        ]);

        return [$courier->fresh(), $order->fresh()];
    }
}
