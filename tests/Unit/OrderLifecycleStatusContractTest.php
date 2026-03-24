<?php

namespace Tests\Unit;

use App\Models\Order;
use Tests\TestCase;

class OrderLifecycleStatusContractTest extends TestCase
{
    public function test_api_create_default_status_and_payment_are_not_dispatchable_for_accept_flow(): void
    {
        $order = (new Order())->forceFill([
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'courier_id' => null,
        ]);

        $this->assertFalse($order->canBeAccepted());
    }

    public function test_searching_paid_order_is_dispatchable_for_accept_flow(): void
    {
        $order = (new Order())->forceFill([
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'courier_id' => null,
        ]);

        $this->assertTrue($order->canBeAccepted());
    }

    public function test_order_model_blocks_direct_mass_assignment_and_uses_explicit_create_contracts(): void
    {
        $this->assertSame(['*'], (new Order())->getGuarded());
        $this->assertNotEmpty(Order::CANONICAL_CREATE_COLUMNS);
        $this->assertNotEmpty(Order::LEGACY_WEB_CREATE_COLUMNS);
    }
}
