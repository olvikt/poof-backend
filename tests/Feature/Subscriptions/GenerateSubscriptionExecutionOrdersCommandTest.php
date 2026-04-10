<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Models\ClientAddress;
use App\Models\ClientSubscription;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GenerateSubscriptionExecutionOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_due_execution_order_and_moves_next_run_forward(): void
    {
        $subscription = $this->createPaidSubscription([
            'next_run_at' => now()->subDays(3),
        ]);

        Artisan::call('subscriptions:generate-execution-orders --limit=100');

        $this->assertDatabaseHas('orders', [
            'subscription_id' => $subscription->id,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'payment_status' => Order::PAY_PENDING,
            'status' => Order::STATUS_NEW,
        ]);

        $subscription->refresh();
        $this->assertTrue($subscription->next_run_at->isFuture());
    }

    public function test_it_does_not_create_duplicate_pending_order_for_same_subscription(): void
    {
        $subscription = $this->createPaidSubscription([
            'next_run_at' => now()->subDay(),
        ]);

        Order::createForTesting([
            'client_id' => $subscription->client_id,
            'subscription_id' => $subscription->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'address_text' => 'вул. Підписки, 10',
            'price' => 450,
            'client_charge_amount' => 450,
        ]);

        Artisan::call('subscriptions:generate-execution-orders --limit=100');

        $this->assertSame(1, Order::query()
            ->where('subscription_id', $subscription->id)
            ->where('payment_status', Order::PAY_PENDING)
            ->count());
    }

    private function createPaidSubscription(array $overrides = []): ClientSubscription
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $plan = SubscriptionPlan::factory()->create([
            'monthly_price' => 450,
            'max_bags' => 2,
            'frequency_type' => 'every_3_days',
        ]);

        $address = ClientAddress::createForUser($client->id, [
            'label' => 'home',
            'title' => 'Дім',
            'address_text' => 'вул. Підписки, 10',
            'city' => 'Київ',
            'street' => 'Підписки',
            'house' => '10',
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        $subscription = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create(array_merge([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $address->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'next_run_at' => now()->subDay(),
            'last_run_at' => now()->subDays(4),
            'ends_at' => now()->addDays(20),
            'auto_renew' => true,
            'renewals_count' => 1,
        ], $overrides)));

        Order::createForTesting([
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'address_text' => 'вул. Підписки, 10',
            'price' => 450,
            'client_charge_amount' => 450,
        ]);

        return $subscription;
    }
}
