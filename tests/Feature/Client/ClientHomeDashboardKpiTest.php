<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\ClientAddress;
use App\Models\ClientSubscription;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientHomeDashboardKpiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_dashboard_renders_new_subscriptions_and_payments_cards_with_correct_kpis_and_links(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $otherClient = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $plan = SubscriptionPlan::factory()->create([
            'name' => 'Місячний',
            'monthly_price' => 500,
        ]);

        $clientAddress = ClientAddress::createForUser($client->id, [
            'label' => 'home',
            'title' => 'Дім',
            'address_text' => 'вул. Клієнтська, 1',
            'city' => 'Київ',
            'street' => 'Клієнтська',
            'house' => '1',
            'lat' => 50.45,
            'lng' => 30.52,
        ]);


        ClientAddress::createForUser($client->id, [
            'label' => 'work',
            'title' => 'Робота',
            'address_text' => 'вул. Клієнтська, 2',
            'city' => 'Київ',
            'street' => 'Клієнтська',
            'house' => '2',
            'lat' => 50.451,
            'lng' => 30.521,
        ]);

        $otherAddress = ClientAddress::createForUser($otherClient->id, [
            'label' => 'home',
            'title' => 'Дім',
            'address_text' => 'вул. Чужа, 99',
            'city' => 'Київ',
            'street' => 'Чужа',
            'house' => '99',
            'lat' => 50.46,
            'lng' => 30.53,
        ]);

        $activeSubscription = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $clientAddress->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'ends_at' => now()->addDays(14),
            'auto_renew' => true,
        ]));

        ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $clientAddress->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'ends_at' => now()->addDays(7),
            'auto_renew' => true,
        ]));

        $pausedSubscription = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $clientAddress->id,
            'status' => ClientSubscription::STATUS_PAUSED,
            'ends_at' => now()->addDays(10),
            'auto_renew' => true,
        ]));

        $otherActiveSubscription = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $otherClient->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $otherAddress->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'ends_at' => now()->addDays(20),
            'auto_renew' => true,
        ]));

        Order::createForTesting([
            'client_id' => $client->id,
            'subscription_id' => $activeSubscription->id,
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_DONE,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'price' => 600,
            'client_charge_amount' => 500,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'address_text' => 'вул. Клієнтська, 1',
        ]);

        Order::createForTesting([
            'client_id' => $client->id,
            'subscription_id' => $pausedSubscription->id,
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_DONE,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'price' => 700,
            'client_charge_amount' => 0,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'address_text' => 'вул. Клієнтська, 1',
        ]);

        Order::createForTesting([
            'client_id' => $client->id,
            'payment_status' => Order::PAY_PENDING,
            'status' => Order::STATUS_NEW,
            'order_type' => Order::TYPE_ONE_TIME,
            'price' => 900,
            'client_charge_amount' => 900,
            'origin' => Order::ORIGIN_CHECKOUT,
            'address_text' => 'вул. Клієнтська, 1',
        ]);

        Order::createForTesting([
            'client_id' => $otherClient->id,
            'subscription_id' => $otherActiveSubscription->id,
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_DONE,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'price' => 2000,
            'client_charge_amount' => 2000,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'address_text' => 'вул. Чужа, 99',
        ]);

        $response = $this->actingAs($client, 'web')->get(route('client.home'));

        $response->assertOk()
            ->assertSee('Підписки')
            ->assertSee('Оплати')
            ->assertSee('Активних')
            ->assertSee('Сплачено')
            ->assertSee('<p class="text-3xl font-extrabold leading-none text-gray-200">1</p>', false)
            ->assertSee('1 200 ₴')
            ->assertSee(route('client.subscriptions'), false)
            ->assertSee(route('client.billing'), false)
            ->assertSee('Мої<br>Адреси', false)
            ->assertSee('Мої<br>Замовлення', false)
            ->assertSee('Тех<br>Підтримка', false)
            ->assertSee('Про<br>Сервіс', false)
            ->assertDontSee('2 000 ₴');

        $this->actingAs($client, 'web')->get(route('client.subscriptions'))->assertOk();
        $this->actingAs($client, 'web')->get(route('client.billing'))->assertOk();
    }
}
