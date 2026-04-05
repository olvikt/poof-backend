<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Client\OrderCreate;
use App\Models\ClientAddress;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCreateCriticalFlowsRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_date_and_time_slot_selection_keeps_runtime_contract_for_today_tomorrow_and_custom_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-29 09:30:00'));

        $user = User::factory()->create();
        $this->actingAs($user);

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $customDate = now()->addDays(3)->toDateString();

        Livewire::test(OrderCreate::class)
            ->assertSet('scheduled_date', $today)
            ->assertSet('timeSlot', 1)
            ->assertSet('scheduled_time_from', '10:00')
            ->assertSet('scheduled_time_to', '12:00')
            ->dispatch('set-scheduled-date', date: $tomorrow)
            ->assertSet('isCustomDate', false)
            ->assertSet('timeSlot', 0)
            ->assertSet('scheduled_time_from', '08:00')
            ->dispatch('set-scheduled-date', date: $customDate)
            ->assertSet('isCustomDate', true)
            ->dispatch('set-time-slot', index: 5)
            ->assertSet('scheduled_time_from', '18:00')
            ->assertSet('scheduled_time_to', '20:00');
    }

    public function test_handover_bags_and_welcome_benefit_pricing_flow_is_stable_and_observable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->call('selectBags', 3)
            ->assertSet('bags_count', 3)
            ->assertSet('price', Order::calcPriceByBags(3))
            ->set('handover_type', Order::HANDOVER_HAND)
            ->assertSet('handover_type', Order::HANDOVER_HAND)
            ->call('selectTrial', 1)
            ->assertSet('is_trial', true)
            ->assertSet('trial_days', 1)
            ->assertSet('bags_count', 1)
            ->assertSet('price', 0)
            ->call('selectBags', 2)
            ->assertSet('is_trial', false)
            ->assertSet('trial_days', 1)
            ->assertSet('bags_count', 2)
            ->assertSet('price', Order::calcPriceByBags(2));
    }

    public function test_subscription_popup_can_be_opened_and_plan_selection_is_persisted_in_component_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->assertSet('showSubscriptionModal', false)
            ->call('openSubscriptionModal')
            ->assertSet('showSubscriptionModal', true)
            ->call('selectSubscriptionPlan', (int) SubscriptionPlan::query()->where('slug', 'every-3-days')->value('id'))
            ->assertSet('showSubscriptionModal', false)
            ->assertSet('subscription_frequency', 'every_3_days');
    }

    public function test_trial_flag_is_blocked_when_user_has_previous_trial_order(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Order::createForTesting([
            'client_id' => $user->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'is_trial' => true,
            'trial_days' => 1,
            'address_text' => 'вул. Пробна, 1',
            'scheduled_date' => now()->toDateString(),
            'scheduled_time_from' => '10:00',
            'scheduled_time_to' => '12:00',
            'price' => 0,
        ]);

        Livewire::test(OrderCreate::class)
            ->assertSet('trial_used', true)
            ->call('selectTrial', 1)
            ->assertSet('is_trial', false)
            ->assertSet('showTrialBlockedModal', true);
    }

    public function test_saved_address_selection_hydrates_order_create_without_resetting_precision(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $address = ClientAddress::query()->create([
            'user_id' => $user->id,
            'label' => 'home',
            'building_type' => 'apartment',
            'address_text' => 'Main Street 7A, Kyiv',
            'city' => 'Kyiv',
            'street' => 'Main Street',
            'house' => '7A',
            'lat' => 50.45,
            'lng' => 30.52,
            'entrance' => '2',
            'floor' => '5',
            'apartment' => '21',
        ]);

        Livewire::test(OrderCreate::class)
            ->call('selectAddress', $address->id)
            ->assertSet('address_id', $address->id)
            ->assertSet('street', 'Main Street')
            ->assertSet('house', '7A')
            ->assertSet('lat', 50.45)
            ->assertSet('lng', 30.52)
            ->assertSet('coordsFromAddressBook', true)
            ->assertSet('address_precision', 'exact')
            ->assertDispatched('sheet:close', name: 'addressPicker');
    }

    public function test_checkout_auto_cancel_copy_mentions_refund(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(OrderCreate::class)
            ->assertSee('Скасувати замовлення та повернути кошти, якщо курʼєра не буде знайдено вчасно')
            ->assertDontSee('Скасувати замовлення, якщо курʼєра не буде знайдено вчасно');
    }
}
