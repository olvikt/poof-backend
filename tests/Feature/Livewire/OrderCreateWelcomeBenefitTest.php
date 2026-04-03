<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Client\OrderCreate;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCreateWelcomeBenefitTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_client_can_create_first_order_for_free_with_system_funding_attributes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->set('address_text', 'вул. Нова, 1')
            ->set('lat', 50.45)
            ->set('lng', 30.52)
            ->set('scheduled_date', now()->toDateString())
            ->set('scheduled_time_from', '10:00')
            ->set('scheduled_time_to', '12:00')
            ->call('selectTrial', 1)
            ->call('submit')
            ->assertSet('showPaymentModal', true);

        $order = Order::query()->latest('id')->firstOrFail();

        $this->assertSame(Order::PAY_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_NEW, $order->status);
        $this->assertTrue($order->is_trial);
        $this->assertSame(Order::BENEFIT_WELCOME_FIRST_ORDER_FREE, $order->benefit_type);
        $this->assertSame(Order::FUNDING_SYSTEM_PROMO, $order->funding_source);
        $this->assertSame(0, (int) $order->price);
        $this->assertSame(0, (int) $order->client_charge_amount);
        $this->assertGreaterThan(0, (int) $order->courier_payout_amount);
        $this->assertSame((int) $order->courier_payout_amount, (int) $order->system_subsidy_amount);
    }

    public function test_welcome_benefit_is_blocked_for_second_use(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Order::createForTesting([
            'client_id' => $user->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'is_trial' => true,
            'trial_days' => 1,
            'benefit_type' => Order::BENEFIT_WELCOME_FIRST_ORDER_FREE,
            'funding_source' => Order::FUNDING_SYSTEM_PROMO,
            'client_charge_amount' => 0,
            'courier_payout_amount' => 120,
            'system_subsidy_amount' => 120,
            'origin' => Order::ORIGIN_CHECKOUT,
            'address_text' => 'вул. Перша, 1',
            'scheduled_date' => now()->toDateString(),
            'scheduled_time_from' => '10:00',
            'scheduled_time_to' => '12:00',
            'price' => 0,
        ]);

        Livewire::test(OrderCreate::class)
            ->assertSet('trial_used', true)
            ->call('selectTrial', 1)
            ->assertSet('showTrialBlockedModal', true)
            ->assertSet('is_trial', false);
    }
}
