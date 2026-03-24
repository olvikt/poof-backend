<?php

namespace Tests\Unit\Orders;

use App\Actions\Orders\Lifecycle\CancelOrderAction;
use App\Actions\Orders\Lifecycle\MarkOrderAsPaidAction;
use App\Events\OrderCreated;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LifecycleActionContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_action_transitions_order_and_repairs_courier_runtime_state_for_accepted_order(): void
    {
        [$courier, $order] = $this->createAcceptedOrderWithCourier();

        $courier->markBusy();

        $result = app(CancelOrderAction::class)->handle($order->fresh());

        $this->assertTrue($result);

        $order->refresh();
        $courier->refresh();

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
        $this->assertFalse((bool) $courier->is_busy);
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(User::SESSION_READY, $courier->session_state);
    }

    public function test_cancel_action_rejects_in_progress_order_without_mutating_runtime_state(): void
    {
        [$courier, $order] = $this->createAcceptedOrderWithCourier();

        $order->update([
            'status' => Order::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);
        $courier->markDelivering();

        $result = app(CancelOrderAction::class)->handle($order->fresh());

        $this->assertFalse($result);

        $order->refresh();
        $courier->refresh();

        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->status);
        $this->assertSame(Courier::STATUS_DELIVERING, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_IN_PROGRESS, $courier->session_state);
    }

    public function test_mark_as_paid_action_sets_dispatchable_state_and_emits_order_created_event(): void
    {
        Event::fake([OrderCreated::class]);

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'address' => 'вул. Оплатна, 1',
            'address_text' => 'вул. Оплатна, 1',
            'price' => 100,
        ]);

        app(MarkOrderAsPaidAction::class)->handle($order->fresh());

        $order->refresh();

        $this->assertSame(Order::PAY_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_SEARCHING, $order->status);

        Event::assertDispatched(OrderCreated::class, function (OrderCreated $event) use ($order) {
            return (int) $event->order->id === (int) $order->id;
        });
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function createAcceptedOrderWithCourier(): array
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => false,
            'is_online' => true,
            'session_state' => User::SESSION_READY,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'accepted_at' => now(),
            'address' => 'вул. Тестова, 1',
            'address_text' => 'вул. Тестова, 1',
            'price' => 100,
        ]);

        return [$courier, $order];
    }
}
