<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\SubscriptionsPage;
use App\Models\ClientAddress;
use App\Models\ClientSubscription;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientMoreMenuModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_more_menu_contains_client_module_links_and_support_redirect(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $response = $this->actingAs($client, 'web')->get(route('client.home'));

        $response->assertOk()
            ->assertSee(route('client.subscriptions'), false)
            ->assertSee(route('client.addresses'), false)
            ->assertSee(route('client.billing'), false)
            ->assertSee(route('client.more.placeholder', ['page' => 'promocodes']), false)
            ->assertSee(route('client.more.placeholder', ['page' => 'settings']), false)
            ->assertSee(route('client.support'), false);

        $this->actingAs($client, 'web')
            ->get(route('client.support'))
            ->assertRedirect('https://t.me/poofsupport');
    }

    public function test_subscriptions_page_shows_only_current_client_subscriptions_and_renew_button(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $other = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $plan = SubscriptionPlan::factory()->create([
            'name' => 'Турбо',
            'frequency_type' => 'every_3_days',
            'monthly_price' => 450,
        ]);

        $address = ClientAddress::createForUser($client->id, [
            'label' => 'home',
            'title' => 'Дім',
            'address_text' => 'вул. Квітнева, 7',
            'city' => 'Київ',
            'street' => 'Квітнева',
            'house' => '7',
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        $otherAddress = ClientAddress::createForUser($other->id, [
            'label' => 'home',
            'title' => 'Дім',
            'address_text' => 'вул. Чужа, 3',
            'city' => 'Київ',
            'street' => 'Чужа',
            'house' => '3',
            'lat' => 50.46,
            'lng' => 30.53,
        ]);

        ClientSubscription::unguarded(function () use ($client, $plan, $address): void {
            ClientSubscription::query()->create([
                'client_id' => $client->id,
                'subscription_plan_id' => $plan->id,
                'address_id' => $address->id,
                'status' => ClientSubscription::STATUS_ACTIVE,
                'ends_at' => now()->addDays(5),
                'auto_renew' => true,
                'renewals_count' => 1,
            ]);
        });

        $ownSubscription = ClientSubscription::query()
            ->where('client_id', $client->id)
            ->firstOrFail();

        Order::createForTesting([
            'client_id' => $client->id,
            'subscription_id' => $ownSubscription->id,
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_DONE,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'price' => 450,
            'client_charge_amount' => 450,
            'address_text' => 'вул. Квітнева, 7',
        ]);

        ClientSubscription::unguarded(function () use ($other, $plan, $otherAddress): void {
            ClientSubscription::query()->create([
                'client_id' => $other->id,
                'subscription_plan_id' => $plan->id,
                'address_id' => $otherAddress->id,
                'status' => ClientSubscription::STATUS_ACTIVE,
                'ends_at' => now()->addDays(6),
                'auto_renew' => true,
                'renewals_count' => 0,
            ]);
        });

        $this->actingAs($client, 'web')
            ->get(route('client.subscriptions'))
            ->assertOk()
            ->assertSee('вул. Квітнева, 7')
            ->assertDontSee('вул. Чужа, 3')
            ->assertSee('Продовжити');
    }

    public function test_subscriptions_page_marks_unpaid_subscription_and_shows_pay_cta(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $plan = SubscriptionPlan::factory()->create([
            'monthly_price' => 450,
        ]);

        $address = ClientAddress::createForUser($client->id, [
            'label' => 'home',
            'title' => 'Дім',
            'address_text' => 'вул. Неоплачена, 1',
            'city' => 'Київ',
            'street' => 'Неоплачена',
            'house' => '1',
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        ClientSubscription::unguarded(function () use ($client, $plan, $address): void {
            ClientSubscription::query()->create([
                'client_id' => $client->id,
                'subscription_plan_id' => $plan->id,
                'address_id' => $address->id,
                'status' => ClientSubscription::STATUS_ACTIVE,
                'ends_at' => now()->addDays(14),
                'auto_renew' => true,
            ]);
        });

        $this->actingAs($client, 'web')
            ->get(route('client.subscriptions'))
            ->assertOk()
            ->assertSee('Не оплачена')
            ->assertSee('Оплатити')
            ->assertDontSee('Продовжити');
    }

    public function test_subscriptions_page_marks_paid_subscription_as_active(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $plan = SubscriptionPlan::factory()->create([
            'monthly_price' => 450,
        ]);

        $subscription = ClientSubscription::unguarded(function () use ($client, $plan): ClientSubscription {
            return ClientSubscription::query()->create([
                'client_id' => $client->id,
                'subscription_plan_id' => $plan->id,
                'status' => ClientSubscription::STATUS_ACTIVE,
                'ends_at' => now()->addDays(14),
                'auto_renew' => true,
            ]);
        });

        Order::createForTesting([
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_DONE,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'price' => 450,
            'client_charge_amount' => 450,
            'address_text' => 'вул. Платіжна, 7',
        ]);

        $this->actingAs($client, 'web')
            ->get(route('client.subscriptions'))
            ->assertOk()
            ->assertSee('Активна')
            ->assertSee('Продовжити')
            ->assertDontSee('Не оплачена');
    }

    public function test_subscription_pause_and_cancel_actions_change_status_without_server_error(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $plan = SubscriptionPlan::factory()->create();

        $subscription = ClientSubscription::unguarded(function () use ($client, $plan): ClientSubscription {
            return ClientSubscription::query()->create([
                'client_id' => $client->id,
                'subscription_plan_id' => $plan->id,
                'status' => ClientSubscription::STATUS_ACTIVE,
                'ends_at' => now()->addDays(14),
                'auto_renew' => true,
            ]);
        });

        Order::createForTesting([
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_DONE,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'price' => 450,
            'client_charge_amount' => 450,
            'address_text' => 'вул. Платіжна, 8',
        ]);

        $this->actingAs($client, 'web');

        Livewire::test(SubscriptionsPage::class)
            ->call('pause', $subscription->id)
            ->assertSet('stats.paused', 1)
            ->assertSet('stats.active', 0)
            ->call('cancel', $subscription->id)
            ->assertSet('stats.completed', 1);

        $subscription->refresh();

        $this->assertSame(ClientSubscription::STATUS_CANCELLED, $subscription->status);
        $this->assertFalse((bool) $subscription->auto_renew);
    }

    public function test_addresses_page_uses_same_saved_addresses_source_as_profile(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        ClientAddress::createForUser($client->id, [
            'label' => 'home',
            'title' => 'Дім',
            'address_text' => 'вул. Синхронна, 11',
            'city' => 'Львів',
            'street' => 'Синхронна',
            'house' => '11',
            'lat' => 49.84,
            'lng' => 24.03,
        ]);

        $addressesPage = $this->actingAs($client, 'web')->get(route('client.addresses'));
        $profilePage = $this->actingAs($client, 'web')->get(route('client.profile'));

        $addressesPage->assertOk()->assertSee('вул. Синхронна, 11');
        $profilePage->assertOk()->assertSee('вул. Синхронна, 11');
    }

    public function test_payments_page_shows_financial_statistics_from_orders_data(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'order_type' => Order::TYPE_ONE_TIME,
            'price' => 220,
            'client_charge_amount' => 220,
            'address_text' => 'вул. Оплати, 1',
        ]);

        Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'price' => 500,
            'client_charge_amount' => 500,
            'subscription_id' => 77,
            'address_text' => 'вул. Оплати, 2',
        ]);

        Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'price' => 180,
            'client_charge_amount' => 180,
            'address_text' => 'вул. Оплати, 3',
        ]);

        $this->actingAs($client, 'web')
            ->get(route('client.billing'))
            ->assertOk()
            ->assertSee('Витрачено всього')
            ->assertSee('720 ₴')
            ->assertSee('Оплачено замовлень')
            ->assertSee('2')
            ->assertSee('Оплачено підписок')
            ->assertSee('1');
    }

    public function test_promocodes_and_settings_placeholders_are_rendered(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $this->actingAs($client, 'web')
            ->get(route('client.more.placeholder', ['page' => 'promocodes']))
            ->assertOk()
            ->assertSee('Промокоди скоро з\'являться');

        $this->actingAs($client, 'web')
            ->get(route('client.more.placeholder', ['page' => 'settings']))
            ->assertOk()
            ->assertSee('Налаштування в розробці');
    }

    public function test_more_pages_have_close_button_and_return_to_more_context(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $this->actingAs($client, 'web')
            ->get(route('client.subscriptions', ['open_more' => 1]))
            ->assertOk()
            ->assertSee('Закрити')
            ->assertSee(route('client.home', ['open_more' => 1]), false);

        $this->actingAs($client, 'web')
            ->get(route('client.addresses', ['open_more' => 1]))
            ->assertOk()
            ->assertSee('Закрити')
            ->assertSee(route('client.home', ['open_more' => 1]), false);

        $this->actingAs($client, 'web')
            ->get(route('client.billing', ['open_more' => 1]))
            ->assertOk()
            ->assertSee('Закрити')
            ->assertSee(route('client.home', ['open_more' => 1]), false);

        $this->actingAs($client, 'web')
            ->get(route('client.more.placeholder', ['page' => 'promocodes', 'open_more' => 1]))
            ->assertOk()
            ->assertSee('Закрити')
            ->assertSee(route('client.home', ['open_more' => 1]), false);
    }
}
