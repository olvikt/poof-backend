<?php

declare(strict_types=1);

namespace Tests\Feature\Subscriptions;

use App\Models\ClientAddress;
use App\Models\ClientSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DetectDuplicateActiveSubscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_duplicate_active_scope_rows(): void
    {
        [$first, $second] = $this->createDuplicateActiveScopePair();

        Artisan::call('subscriptions:detect-duplicate-active --dry-run');

        $payload = json_decode((string) Artisan::output(), true);

        $this->assertSame(1, $payload['duplicate_scope_count']);
        $this->assertSame(1, $payload['duplicate_subscription_count']);
        $this->assertSame([$second->id], $payload['duplicate_ids']);
        $this->assertSame($first->id, $payload['scopes'][0]['keeper_id']);
    }

    public function test_it_pauses_duplicates_when_remediation_enabled(): void
    {
        [$first, $second] = $this->createDuplicateActiveScopePair();

        Artisan::call('subscriptions:detect-duplicate-active --remediate');

        $this->assertSame(ClientSubscription::STATUS_ACTIVE, $first->fresh()?->status);
        $this->assertSame(ClientSubscription::STATUS_PAUSED, $second->fresh()?->status);
        $this->assertNotNull($second->fresh()?->paused_at);
    }

    /**
     * @return array{0: ClientSubscription, 1: ClientSubscription}
     */
    private function createDuplicateActiveScopePair(): array
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $plan = SubscriptionPlan::factory()->create();

        $address = ClientAddress::createForUser($client->id, [
            'label' => 'home',
            'title' => 'Дім',
            'address_text' => 'вул. Дублікатна, 1',
            'city' => 'Київ',
            'street' => 'Дублікатна',
            'house' => '1',
            'lat' => 50.45,
            'lng' => 30.52,
        ]);

        $first = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $address->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'active_scope_key' => null,
            'next_run_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
        ]));

        $second = ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'address_id' => $address->id,
            'status' => ClientSubscription::STATUS_PAUSED,
            'next_run_at' => now()->addDays(2),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
        ]));

        DB::table('client_subscriptions')
            ->where('id', $second->id)
            ->update([
                'status' => ClientSubscription::STATUS_ACTIVE,
                'paused_at' => null,
                'active_scope_key' => null,
            ]);

        return [$first, $second];
    }
}
