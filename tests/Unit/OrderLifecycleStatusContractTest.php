<?php

namespace Tests\Unit;

use App\Models\Order;
use Tests\TestCase;

class OrderLifecycleStatusContractTest extends TestCase
{
    public function test_api_create_default_status_and_payment_are_not_dispatchable_for_accept_flow(): void
    {
        $order = new Order([
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'courier_id' => null,
        ]);

        $this->assertFalse($order->canBeAccepted());
    }

    public function test_searching_paid_order_is_dispatchable_for_accept_flow(): void
    {
        $order = new Order([
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'courier_id' => null,
        ]);

        $this->assertTrue($order->canBeAccepted());
    }

    public function test_order_model_keeps_legacy_web_create_fields_mass_assignable_for_livewire_flow(): void
    {
        $fillable = (new Order())->getFillable();

        $this->assertContains('order_type', $fillable);
        $this->assertContains('scheduled_time_from', $fillable);
        $this->assertContains('scheduled_time_to', $fillable);
        $this->assertContains('handover_type', $fillable);
        $this->assertContains('address_text', $fillable);
    }
}
