<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Models\ClientAddress;
use App\Models\ClientSubscription;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GenerateSubscriptionExecutionOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_generates_nearest_valid_current_slot_for_overdue_subscription(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00'));

        $subscription = $this->createPaidSubscription([
            'next_run_at' => now()->subDays(10),
        ]);

        Artisan::call('subscriptions:generate-execution-orders --limit=100');

        $this->assertDatabaseHas('orders', [
            'subscription_id' => $subscription->id,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_SEARCHING,
            'scheduled_date' => '2026-04-12',
            'scheduled_time_from' => '12:00:00',
        ]);

        $subscription->refresh();
        $this->assertSame('2026-04-15 12:00:00', $subscription->next_run_at?->format('Y-m-d H:i:s'));
    }

    public function test_it_does_not_create_duplicate_order_for_existing_pending_target_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00'));

        $subscription = $this->createPaidSubscription([
            'next_run_at' => Carbon::parse('2026-04-09 12:00:00'),
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
            'scheduled_date' => '2026-04-12',
            'scheduled_time_from' => '12:00',
            'scheduled_time_to' => '14:00',
        ]);

        Artisan::call('subscriptions:generate-execution-orders --limit=100');

        $this->assertSame(1, Order::query()
            ->where('subscription_id', $subscription->id)
            ->where('payment_status', Order::PAY_PENDING)
            ->count());

        $subscription->refresh();
        $this->assertSame('2026-04-09 12:00:00', $subscription->next_run_at?->format('Y-m-d H:i:s'));
    }

    public function test_it_blocks_new_generation_when_any_unresolved_pending_order_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00'));

        $subscription = $this->createPaidSubscription([
            'next_run_at' => Carbon::parse('2026-04-01 12:00:00'),
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
            'scheduled_date' => '2026-04-01',
            'scheduled_time_from' => '12:00',
            'scheduled_time_to' => '14:00',
        ]);

        Artisan::call('subscriptions:generate-execution-orders --limit=100');

        $this->assertSame(1, Order::query()
            ->where('subscription_id', $subscription->id)
            ->where('payment_status', Order::PAY_PENDING)
            ->count());

        $subscription->refresh();
        $this->assertSame('2026-04-01 12:00:00', $subscription->next_run_at?->format('Y-m-d H:i:s'));
    }

    public function test_it_treats_pending_order_in_same_minute_as_duplicate_even_with_non_zero_seconds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00'));

        $subscription = $this->createPaidSubscription([
            'next_run_at' => Carbon::parse('2026-04-09 12:00:00'),
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
            'scheduled_date' => '2026-04-12',
            'scheduled_time_from' => '12:00:30',
            'scheduled_time_to' => '14:00',
        ]);

        Artisan::call('subscriptions:generate-execution-orders --limit=100');

        $this->assertSame(1, Order::query()
            ->where('subscription_id', $subscription->id)
            ->where('payment_status', Order::PAY_PENDING)
            ->count());
    }

    public function test_it_does_not_create_second_pending_order_when_slot_duplicate_is_detected_without_global_pending_guard(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00'));

        $subscription = $this->createPaidSubscription([
            'next_run_at' => Carbon::parse('2026-04-09 12:00:00'),
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
            'scheduled_date' => '2026-04-12',
            'scheduled_time_from' => '12:00:15',
            'scheduled_time_to' => '14:00',
        ]);

        // Simulate stale unresolved order outside "pending" scope so only slot-level uniqueness must protect us.
        Order::query()
            ->where('subscription_id', $subscription->id)
            ->where('origin', Order::ORIGIN_SUBSCRIPTION)
            ->where('payment_status', Order::PAY_PENDING)
            ->update(['payment_status' => Order::PAY_PAID]);

        Artisan::call('subscriptions:generate-execution-orders --limit=100');

        $this->assertSame(1, Order::query()
            ->where('subscription_id', $subscription->id)
            ->where('origin', Order::ORIGIN_SUBSCRIPTION)
            ->whereDate('scheduled_date', '2026-04-12')
            ->count(), 'Slot-level uniqueness must block duplicate execution orders even when no global pending order remains.');
    }

    public function test_it_creates_due_execution_order_for_legacy_active_subscription_with_auto_renew_disabled(): void
    {
        $subscription = $this->createPaidSubscription([
            'next_run_at' => now()->subDays(2),
            'auto_renew' => false,
        ]);

        Artisan::call('subscriptions:generate-execution-orders --limit=100');

        $this->assertDatabaseHas('orders', [
            'subscription_id' => $subscription->id,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_SEARCHING,
        ]);
    }

    public function test_repeat_runs_do_not_duplicate_same_slot_and_do_not_stall_on_backlog(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00'));

        $subscription = $this->createPaidSubscription([
            'next_run_at' => Carbon::parse('2026-04-01 12:00:00'),
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
            'scheduled_date' => '2026-04-01',
            'scheduled_time_from' => '12:00',
            'scheduled_time_to' => '14:00',
        ]);

        Artisan::call('subscriptions:generate-execution-orders --limit=100');
        Artisan::call('subscriptions:generate-execution-orders --limit=100');

        $this->assertSame(1, Order::query()
            ->where('subscription_id', $subscription->id)
            ->where('payment_status', Order::PAY_PENDING)
            ->count());
    }


    public function test_it_allocates_execution_price_from_monthly_subscription_amount(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00'));

        $subscription = $this->createPaidSubscription([
            'next_run_at' => Carbon::parse('2026-04-10 12:00:00'),
            'ends_at' => Carbon::parse('2026-05-01 00:00:00'),
        ], [
            'monthly_price' => 45,
            'pickups_per_month' => 10,
            'frequency_type' => 'every_3_days',
        ]);

        Artisan::call('subscriptions:generate-execution-orders --limit=100');

        $order = Order::query()->where('subscription_id', $subscription->id)->latest('id')->firstOrFail();

        $this->assertNotSame(45, (int) $order->price);
        $this->assertSame(4, (int) $order->price);
        $this->assertSame((int) $order->price, (int) $order->courier_payout_amount);
        $this->assertSame(Order::PAY_PAID, $order->payment_status);
        $this->assertSame(Order::STATUS_SEARCHING, $order->status);
    }

    public function test_execution_allocations_sum_to_monthly_price_with_remainder_on_last_execution(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-01 12:00:00'));

        $subscription = $this->createPaidSubscription([
            'next_run_at' => Carbon::parse('2026-04-01 12:00:00'),
            'ends_at' => Carbon::parse('2026-05-01 00:00:00'),
        ], [
            'monthly_price' => 45,
            'pickups_per_month' => 10,
            'frequency_type' => 'every_3_days',
        ]);

        for ($i = 0; $i < 10; $i++) {
            Carbon::setTestNow(Carbon::parse('2026-04-01 12:00:00')->addDays($i * 3));
            Artisan::call('subscriptions:generate-execution-orders --limit=100');
        }

        $generatedOrders = Order::query()
            ->where('subscription_id', $subscription->id)
            ->where('origin', Order::ORIGIN_SUBSCRIPTION)
            ->whereDate('scheduled_date', '>=', '2026-04-01')
            ->whereDate('scheduled_date', '<', '2026-05-01')
            ->orderBy('scheduled_date')
            ->get();

        $this->assertCount(10, $generatedOrders);
        $this->assertSame([4, 4, 4, 4, 4, 4, 4, 4, 4, 9], $generatedOrders->pluck('price')->all());
        $this->assertSame(45, (int) $generatedOrders->sum('price'));
        $this->assertSame(9, (int) $generatedOrders->last()->price);
    }

    public function test_generated_execution_price_equals_courier_payout_amount(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00'));

        $subscription = $this->createPaidSubscription([
            'next_run_at' => Carbon::parse('2026-04-10 12:00:00'),
            'ends_at' => Carbon::parse('2026-05-01 00:00:00'),
        ], [
            'monthly_price' => 45,
            'pickups_per_month' => 10,
            'frequency_type' => 'every_3_days',
        ]);

        Artisan::call('subscriptions:generate-execution-orders --limit=100');

        $order = Order::query()->where('subscription_id', $subscription->id)->latest('id')->firstOrFail();

        $this->assertSame((int) $order->price, (int) $order->courier_payout_amount);
    }

    public function test_it_does_not_generate_more_than_planned_executions_within_billing_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-01 12:00:00'));

        $subscription = $this->createPaidSubscription([
            'next_run_at' => Carbon::parse('2026-04-01 12:00:00'),
            'ends_at' => Carbon::parse('2026-05-01 00:00:00'),
        ], [
            'monthly_price' => 45,
            'pickups_per_month' => 10,
            'frequency_type' => 'every_3_days',
        ]);

        for ($i = 0; $i < 11; $i++) {
            Carbon::setTestNow(Carbon::parse('2026-04-01 12:00:00')->addDays($i * 3));
            Artisan::call('subscriptions:generate-execution-orders --limit=100');
        }

        $generatedOrders = Order::query()
            ->where('subscription_id', $subscription->id)
            ->where('origin', Order::ORIGIN_SUBSCRIPTION)
            ->whereDate('scheduled_date', '>=', '2026-04-01')
            ->whereDate('scheduled_date', '<', '2026-05-01')
            ->orderBy('scheduled_date')
            ->get();

        $this->assertCount(10, $generatedOrders);
        $this->assertSame([4, 4, 4, 4, 4, 4, 4, 4, 4, 9], $generatedOrders->pluck('price')->all());
        $this->assertSame(45, (int) $generatedOrders->sum('price'));
        $this->assertSame('2026-05-01 12:00:00', $subscription->fresh()->next_run_at?->format('Y-m-d H:i:s'));
    }

    public function test_it_generates_new_period_after_renewal_instead_of_repeating_exhaustion(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-01 12:00:00'));

        $subscription = $this->createPaidSubscription([
            'next_run_at' => Carbon::parse('2026-04-01 12:00:00'),
            'ends_at' => Carbon::parse('2026-05-01 00:00:00'),
        ], [
            'monthly_price' => 45,
            'pickups_per_month' => 10,
            'frequency_type' => 'every_3_days',
        ]);

        for ($i = 0; $i < 11; $i++) {
            Carbon::setTestNow(Carbon::parse('2026-04-01 12:00:00')->addDays($i * 3));
            Artisan::call('subscriptions:generate-execution-orders --limit=100');
        }

        $this->assertSame('2026-05-01 12:00:00', $subscription->fresh()->next_run_at?->format('Y-m-d H:i:s'));

        $subscription->forceFill([
            'ends_at' => Carbon::parse('2026-06-01 00:00:00'),
        ])->save();

        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00'));
        Artisan::call('subscriptions:generate-execution-orders --limit=100');

        $mayFirstExecution = Order::query()
            ->where('subscription_id', $subscription->id)
            ->whereDate('scheduled_date', '2026-05-01')
            ->where('origin', Order::ORIGIN_SUBSCRIPTION)
            ->first();

        $this->assertNotNull($mayFirstExecution);
        $this->assertSame(4, (int) $mayFirstExecution->price);
    }

    public function test_it_skips_generation_after_period_end_when_not_renewed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00'));

        $subscription = $this->createPaidSubscription([
            'next_run_at' => Carbon::parse('2026-05-01 12:00:00'),
            'ends_at' => Carbon::parse('2026-05-01 00:00:00'),
        ], [
            'monthly_price' => 45,
            'pickups_per_month' => 10,
            'frequency_type' => 'every_3_days',
        ]);

        Artisan::call('subscriptions:generate-execution-orders --limit=100');
        $subscription->refresh();

        $this->assertSame(0, Order::query()
            ->where('subscription_id', $subscription->id)
            ->whereDate('scheduled_date', '2026-05-01')
            ->where('origin', Order::ORIGIN_SUBSCRIPTION)
            ->count());
        $this->assertTrue($subscription->next_run_at !== null && $subscription->next_run_at->greaterThan(Carbon::parse('2026-05-01 12:00:00')));
    }

    private function createPaidSubscription(array $overrides = [], array $planOverrides = []): ClientSubscription
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $plan = SubscriptionPlan::factory()->create(array_merge([
            'monthly_price' => 450,
            'pickups_per_month' => 10,
            'max_bags' => 2,
            'frequency_type' => 'every_3_days',
        ], $planOverrides));

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
            'scheduled_date' => now()->subMonths(2)->toDateString(),
            'scheduled_time_from' => '10:00',
            'scheduled_time_to' => '12:00',
            'price' => 450,
            'client_charge_amount' => 450,
        ]);

        return $subscription;
    }
}
