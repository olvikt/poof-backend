<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\ClientSubscription;
use App\Models\OrderCompletionRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingConfirmationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_execution_awaiting_confirmation_is_counted(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true]);
        $this->createAwaitingOrder($client->id, $courier->id, $this->createSubscriptionForClient($client->id)->id);

        $this->actingAs($client, 'sanctum');
        $this->getJson('/api/client/pending-confirmations')
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.count', 1);
    }

    public function test_one_time_awaiting_confirmation_is_counted(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true]);
        $this->createAwaitingOrder($client->id, $courier->id, null);

        $this->actingAs($client, 'sanctum');
        $this->getJson('/api/client/pending-confirmations')
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.count', 1);
    }

    public function test_pending_confirmations_count_decreases_after_confirm_and_ignores_foreign_orders(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $otherClient = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true]);

        $subscriptionOrder = $this->createAwaitingOrder($client->id, $courier->id, $this->createSubscriptionForClient($client->id)->id);
        $oneTimeOrder = $this->createAwaitingOrder($client->id, $courier->id, null);
        $this->createAwaitingOrder($otherClient->id, $courier->id, $this->createSubscriptionForClient($otherClient->id)->id);

        $this->actingAs($client, 'sanctum');

        $response = $this->getJson('/api/client/pending-confirmations');
        $response->assertOk()
            ->assertJsonPath('data.pending_confirmations.count', 2)
            ->assertJsonCount(2, 'data.pending_confirmations.items');

        $this->postJson('/api/client/orders/'.$subscriptionOrder->public_id.'/completion-proof/confirm')->assertOk();

        $this->getJson('/api/client/pending-confirmations')
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.count', 1)
            ->assertJsonCount(1, 'data.pending_confirmations.items');

        $this->postJson('/api/client/orders/'.$oneTimeOrder->public_id.'/completion-proof/confirm')->assertOk();

        $this->getJson('/api/client/pending-confirmations')
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.count', 0)
            ->assertJsonCount(0, 'data.pending_confirmations.items');
    }

    public function test_disputed_order_is_not_included_in_pending_confirmations_count(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true]);

        $this->createAwaitingOrder($client->id, $courier->id, $this->createSubscriptionForClient($client->id)->id);
        $disputedOrder = $this->createAwaitingOrder($client->id, $courier->id, $this->createSubscriptionForClient($client->id)->id);

        OrderCompletionRequest::query()
            ->where('order_id', $disputedOrder->id)
            ->update(['status' => OrderCompletionRequest::STATUS_DISPUTED]);

        $this->actingAs($client, 'sanctum');

        $this->getJson('/api/client/pending-confirmations')
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.count', 1)
            ->assertJsonCount(1, 'data.pending_confirmations.items');
    }


    public function test_cancelled_or_expired_orders_are_not_included_even_if_completion_request_is_awaiting(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true]);

        $activeOrder = $this->createAwaitingOrder($client->id, $courier->id, null);
        $cancelledOrder = $this->createAwaitingOrder($client->id, $courier->id, null);
        $expiredOrder = $this->createAwaitingOrder($client->id, $courier->id, null);

        $cancelledOrder->forceFill(['status' => Order::STATUS_CANCELLED])->save();
        $expiredOrder->forceFill(['status' => Order::STATUS_EXPIRED])->save();

        $this->actingAs($client, 'sanctum');

        $this->getJson('/api/client/pending-confirmations')
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.count', 1)
            ->assertJsonCount(1, 'data.pending_confirmations.items')
            ->assertJsonPath('data.pending_confirmations.items.0.order_id', $activeOrder->id)
            ->assertJsonPath('data.pending_confirmations.items.0.order_public_id', $activeOrder->public_id);
    }

    private function createAwaitingOrder(int $clientId, int $courierId, ?int $subscriptionId): Order
    {
        $order = Order::createForTesting([
            'client_id' => $clientId,
            'courier_id' => $courierId,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
            'order_type' => $subscriptionId ? Order::TYPE_SUBSCRIPTION : Order::TYPE_ONE_TIME,
            'subscription_id' => $subscriptionId,
            'address_text' => 'Test address',
            'price' => 250,
            'completion_policy' => Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM,
        ]);

        OrderCompletionRequest::unguarded(fn () => OrderCompletionRequest::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courierId,
            'status' => OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION,
            'submitted_at' => now()->subMinutes(30),
            'auto_confirmation_due_at' => now()->addHours(23),
        ]));

        return $order;
    }


    private function createSubscriptionForClient(int $clientId): ClientSubscription
    {
        $plan = SubscriptionPlan::factory()->create();

        return ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $clientId,
            'subscription_plan_id' => $plan->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'frequency_type' => 'weekly',
            'next_run_at' => now()->addDay(),
        ]));
    }

}
