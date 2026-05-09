<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Actions\Orders\Completion\GetPendingConfirmationsForClientAction;
use App\Livewire\Client\OrdersList;
use App\Models\ClientSubscription;
use App\Models\Order;
use App\Models\OrderCompletionRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientPendingConfirmationsBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_returns_target_urls_and_labels_for_one_time_and_subscription_orders(): void
    {
        $client = User::factory()->create();
        $plan = SubscriptionPlan::query()->firstOrFail();
        $subscription = ClientSubscription::unguarded(fn () => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'next_run_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
        ]));

        $oneTime = Order::createForTesting(['client_id' => $client->id, 'subscription_id' => null, 'origin' => Order::ORIGIN_MANUAL, 'order_type' => Order::TYPE_ONE_TIME, 'status' => Order::STATUS_IN_PROGRESS, 'address_text' => 'A']);
        $subOrder = Order::createForTesting(['client_id' => $client->id, 'subscription_id' => $subscription->id, 'origin' => Order::ORIGIN_SUBSCRIPTION, 'order_type' => Order::TYPE_SUBSCRIPTION, 'status' => Order::STATUS_IN_PROGRESS, 'address_text' => 'B']);
        OrderCompletionRequest::factory()->create(['order_id' => $oneTime->id, 'status' => OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION]);
        OrderCompletionRequest::factory()->create(['order_id' => $subOrder->id, 'status' => OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION]);

        $payload = app(GetPendingConfirmationsForClientAction::class)->handle($client);
        $this->assertCount(2, $payload['items']);
        $this->assertNotEmpty(collect($payload['items'])->firstWhere('order_id', $oneTime->id)['target_url']);
        $this->assertStringContainsString('/client/orders', collect($payload['items'])->firstWhere('order_id', $oneTime->id)['target_url']);
        $this->assertSame('Перейти до замовлення', collect($payload['items'])->firstWhere('order_id', $oneTime->id)['target_label']);
        $this->assertStringContainsString('/client/subscriptions', collect($payload['items'])->firstWhere('order_id', $subOrder->id)['target_url']);
        $this->assertSame('Відкрити підписку', collect($payload['items'])->firstWhere('order_id', $subOrder->id)['target_label']);
    }

    public function test_header_renders_dropdown_menu_and_items_when_pending_exists(): void
    {
        $client = User::factory()->create();
        $order = Order::createForTesting(['client_id' => $client->id, 'status' => Order::STATUS_IN_PROGRESS]);
        OrderCompletionRequest::factory()->create(['order_id' => $order->id, 'status' => OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION]);

        $this->actingAs($client)
            ->get(route('client.home'))
            ->assertSee('Потрібно підтвердити')
            ->assertSee('data-e2e="client-confirmation-bell-menu"', false)
            ->assertSee('data-e2e="client-confirmation-bell-item"', false)
            ->assertSee(route('client.orders', ['highlight' => $order->id]), false)
            ->assertSee('line-clamp-2', false);
    }

    public function test_no_pending_confirmations_does_not_render_active_menu(): void
    {
        $client = User::factory()->create();
        $this->actingAs($client)
            ->get(route('client.home'))
            ->assertDontSee('data-e2e="client-confirmation-bell-menu"', false);
    }

    public function test_orders_page_highlight_query_renders_marker(): void
    {
        $client = User::factory()->create();
        $order = Order::createForTesting(['client_id' => $client->id, 'status' => Order::STATUS_IN_PROGRESS]);

        $this->actingAs($client);

        Livewire::withQueryParams(['highlight' => $order->id])
            ->test(OrdersList::class)
            ->assertSeeHtml('data-e2e="highlighted-pending-confirmation-order"');
    }
}
