<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingConfirmationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_confirmations_count_decreases_after_confirm_and_ignores_foreign_orders(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $otherClient = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $subscriptionOrder = $this->createAwaitingOrder($client->id, 101);
        $oneTimeOrder = $this->createAwaitingOrder($client->id, null);
        $this->createAwaitingOrder($otherClient->id, 202);

        $this->actingAs($client, 'sanctum');

        $response = $this->getJson('/api/client/pending-confirmations');
        $response->assertOk()
            ->assertJsonPath('data.pending_confirmations.count', 2)
            ->assertJsonCount(2, 'data.pending_confirmations.items');

        $this->postJson('/api/client/orders/'.$subscriptionOrder->id.'/completion-proof/confirm')->assertOk();

        $this->getJson('/api/client/pending-confirmations')
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.count', 1)
            ->assertJsonCount(1, 'data.pending_confirmations.items');

        $this->postJson('/api/client/orders/'.$oneTimeOrder->id.'/completion-proof/confirm')->assertOk();

        $this->getJson('/api/client/pending-confirmations')
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.count', 0)
            ->assertJsonCount(0, 'data.pending_confirmations.items');
    }

    private function createAwaitingOrder(int $clientId, ?int $subscriptionId): Order
    {
        $order = Order::createForTesting([
            'client_id' => $clientId,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
            'order_type' => $subscriptionId ? Order::TYPE_SUBSCRIPTION : Order::TYPE_ONE_TIME,
            'subscription_id' => $subscriptionId,
            'address_text' => 'Test address',
            'price' => 250,
            'completion_policy' => Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM,
        ]);

        OrderCompletionRequest::query()->create([
            'order_id' => $order->id,
            'status' => OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION,
            'submitted_at' => now()->subMinutes(30),
            'auto_confirmation_due_at' => now()->addHours(23),
        ]);

        return $order;
    }
}
