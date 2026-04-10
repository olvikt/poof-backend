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

class BackfillSubscriptionAutoRenewCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_backfills_only_targeted_paid_active_legacy_subscriptions(): void
    {
        $eligible = $this->createSubscription([
            'status' => ClientSubscription::STATUS_ACTIVE,
            'auto_renew' => false,
        ], hasPaidOrder: true);

        $inactive = $this->createSubscription([
            'status' => ClientSubscription::STATUS_PAUSED,
            'auto_renew' => false,
        ], hasPaidOrder: true);

        $unpaid = $this->createSubscription([
            'status' => ClientSubscription::STATUS_ACTIVE,
            'auto_renew' => false,
        ], hasPaidOrder: false);

        Artisan::call(sprintf(
            'subscriptions:backfill-auto-renew --ids=%d --ids=%d --ids=%d',
            $eligible->id,
            $inactive->id,
            $unpaid->id,
        ));

        $this->assertTrue((bool) $eligible->fresh()->auto_renew);
        $this->assertFalse((bool) $inactive->fresh()->auto_renew);
        $this->assertFalse((bool) $unpaid->fresh()->auto_renew);
    }

    public function test_it_supports_dry_run_without_writing_changes(): void
    {
        $subscription = $this->createSubscription([
            'status' => ClientSubscription::STATUS_ACTIVE,
            'auto_renew' => false,
        ], hasPaidOrder: true);

        Artisan::call(sprintf('subscriptions:backfill-auto-renew --ids=%d --dry-run', $subscription->id));

        $this->assertFalse((bool) $subscription->fresh()->auto_renew);
    }

    private function createSubscription(array $overrides, bool $hasPaidOrder): ClientSubscription
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
            'auto_renew' => false,
            'renewals_count' => 1,
        ], $overrides)));

        if ($hasPaidOrder) {
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
        }

        return $subscription;
    }
}
