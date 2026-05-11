<?php

namespace Tests\Unit\Orders;

use App\Models\Order;
use App\Support\Orders\OrderLifecycleTransitionPolicy;
use Tests\TestCase;

class OrderLifecycleTransitionPolicyTest extends TestCase
{
    public function test_allowed_default_transitions(): void
    {
        $policy = app(OrderLifecycleTransitionPolicy::class);

        $this->assertTrue($policy->canTransition(Order::STATUS_NEW, Order::STATUS_SEARCHING));
        $this->assertTrue($policy->canTransition(Order::STATUS_SEARCHING, Order::STATUS_ACCEPTED));
        $this->assertTrue($policy->canTransition(Order::STATUS_ACCEPTED, Order::STATUS_IN_PROGRESS));
        $this->assertTrue($policy->canTransition(Order::STATUS_IN_PROGRESS, Order::STATUS_DONE));
        $this->assertTrue($policy->canTransition(Order::STATUS_NEW, Order::STATUS_CANCELLED));
        $this->assertTrue($policy->canTransition(Order::STATUS_SEARCHING, Order::STATUS_CANCELLED));
        $this->assertTrue($policy->canTransition(Order::STATUS_SEARCHING, Order::STATUS_EXPIRED));
    }

    public function test_forbidden_transitions_and_terminal_immutability(): void
    {
        $policy = app(OrderLifecycleTransitionPolicy::class);

        $this->assertFalse($policy->canTransition(Order::STATUS_DONE, Order::STATUS_SEARCHING));
        $this->assertFalse($policy->canTransition(Order::STATUS_CANCELLED, Order::STATUS_NEW));
        $this->assertFalse($policy->canTransition(Order::STATUS_EXPIRED, Order::STATUS_ACCEPTED));
        $this->assertFalse($policy->canTransition(Order::STATUS_NEW, Order::STATUS_ACCEPTED));
        $this->assertFalse($policy->canTransition(Order::STATUS_NEW, Order::STATUS_IN_PROGRESS));
        $this->assertFalse($policy->canTransition(Order::STATUS_SEARCHING, Order::STATUS_DONE));
        $this->assertFalse($policy->canTransition(Order::STATUS_ACCEPTED, Order::STATUS_CANCELLED));
        $this->assertFalse($policy->canTransition(Order::STATUS_IN_PROGRESS, Order::STATUS_CANCELLED));
    }

    public function test_admin_override_allows_accepted_or_in_progress_cancellation(): void
    {
        $policy = app(OrderLifecycleTransitionPolicy::class);

        $this->assertTrue($policy->canTransition(Order::STATUS_ACCEPTED, Order::STATUS_CANCELLED, OrderLifecycleTransitionPolicy::FLOW_ADMIN_OVERRIDE));
        $this->assertTrue($policy->canTransition(Order::STATUS_IN_PROGRESS, Order::STATUS_CANCELLED, OrderLifecycleTransitionPolicy::FLOW_ADMIN_OVERRIDE));
    }
}
