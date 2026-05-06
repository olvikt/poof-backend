<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Actions\Orders\Completion\StartOrderCompletionProofAction;
use App\Actions\Orders\Completion\SubmitOrderCompletionByCourierAction;
use App\Actions\Orders\Completion\UploadOrderCompletionProofAction;
use App\Livewire\Client\SubscriptionsPage;
use App\Models\ClientSubscription;
use App\Models\Courier;
use App\Models\CourierEarning;
use App\Models\Order;
use App\Models\OrderCompletionDispute;
use App\Models\OrderCompletionProof;
use App\Models\OrderCompletionRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionsPageCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_awaiting_execution_with_proofs_and_can_confirm_completion(): void
    {
        [$client, $subscription, $order] = $this->seedAwaitingExecutionOrder();

        $this->actingAs($client);

        $component = Livewire::test(SubscriptionsPage::class)
            ->call('openDetails', $subscription->id)
            ->assertSee((string) $order->id)
            ->assertSee('Фотозвіт')
            ->call('confirmExecutionCompletion', $subscription->id, $order->id);

        $request = OrderCompletionRequest::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(OrderCompletionRequest::STATUS_CLIENT_CONFIRMED, $request->status);
        $this->assertSame(Order::STATUS_DONE, $order->fresh()->status);
        $this->assertSame(1, CourierEarning::query()->where('order_id', $order->id)->count());

        $component->call('openDetails', $subscription->id)
            ->assertDontSee('Підтвердити')
            ->assertDontSee('Відкрити спір');
    }

    public function test_owner_can_open_dispute_and_earning_is_not_created(): void
    {
        [$client, $subscription, $order] = $this->seedAwaitingExecutionOrder();

        $this->actingAs($client);

        Livewire::test(SubscriptionsPage::class)
            ->call('openDetails', $subscription->id)
            ->call('disputeExecutionCompletion', $subscription->id, $order->id);

        $request = OrderCompletionRequest::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(OrderCompletionRequest::STATUS_DISPUTED, $request->status);
        $this->assertDatabaseHas('order_completion_disputes', ['order_id' => $order->id]);
        $this->assertSame(0, CourierEarning::query()->where('order_id', $order->id)->count());
    }

    public function test_foreign_client_cannot_confirm_or_dispute_or_spoof_subscription_id(): void
    {
        [$owner, $subscription, $order] = $this->seedAwaitingExecutionOrder();
        [$otherClient, $otherSubscription] = $this->seedSecondClientAndSubscription();

        $this->actingAs($otherClient);

        Livewire::test(SubscriptionsPage::class)
            ->call('confirmExecutionCompletion', $subscription->id, $order->id)
            ->call('disputeExecutionCompletion', $subscription->id, $order->id)
            ->call('confirmExecutionCompletion', $otherSubscription->id, $order->id);

        $request = OrderCompletionRequest::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION, $request->status);
        $this->assertDatabaseMissing('order_completion_disputes', ['order_id' => $order->id]);
        $this->assertSame(0, CourierEarning::query()->where('order_id', $order->id)->count());
        $this->assertSame($owner->id, $order->fresh()->client_id);
    }


    public function test_card_shows_checkout_order_number_and_paid_date(): void
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

        Order::createForTesting([
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'origin' => Order::ORIGIN_CHECKOUT,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'payment_status' => Order::PAY_PAID,
            'paid_at' => CarbonImmutable::create(2026, 5, 6, 9, 30, 0),
            'address_text' => 'Subscription address',
            'price' => 500,
        ]);

        $this->actingAs($client);

        Livewire::test(SubscriptionsPage::class)
            ->assertSee('Замовлення №')
            ->assertSee('Оплачено: 06.05.2026');
    }

    public function test_active_subscription_does_not_show_resume_button(): void
    {
        [$client, $subscription] = $this->seedSubscriptionWithStatus(ClientSubscription::STATUS_ACTIVE, now()->addMonth());

        $this->actingAs($client);

        Livewire::test(SubscriptionsPage::class)
            ->assertDontSee('Продовжити');
    }

    public function test_paused_or_expired_subscription_shows_resume_button(): void
    {
        [$pausedClient] = $this->seedSubscriptionWithStatus(ClientSubscription::STATUS_PAUSED, now()->addMonth());
        [$expiredClient] = $this->seedSubscriptionWithStatus(ClientSubscription::STATUS_ACTIVE, now()->subDay());

        $this->actingAs($pausedClient);
        Livewire::test(SubscriptionsPage::class)->assertSee('Продовжити');

        $this->actingAs($expiredClient);
        Livewire::test(SubscriptionsPage::class)->assertSee('Продовжити');
    }
    private function seedAwaitingExecutionOrder(): array
    {
        $client = User::factory()->create();
        $courierUser = User::factory()->create();
        Courier::query()->create(['user_id' => $courierUser->id, 'status' => Courier::STATUS_ONLINE]);

        $plan = SubscriptionPlan::query()->firstOrFail();

        $subscription = ClientSubscription::unguarded(fn () => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'next_run_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => true,
        ]));

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courierUser->id,
            'subscription_id' => $subscription->id,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
            'completion_policy' => Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM,
            'address_text' => 'Subscription address',
            'price' => 500,
        ]);

        app(StartOrderCompletionProofAction::class)->handle($order, $courierUser);
        app(UploadOrderCompletionProofAction::class)->handle($order, $courierUser, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door.jpg');
        app(UploadOrderCompletionProofAction::class)->handle($order, $courierUser, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'proofs/container.jpg');
        app(SubmitOrderCompletionByCourierAction::class)->handle($order, $courierUser);

        return [$client, $subscription, $order->fresh()];
    }


    private function seedSubscriptionWithStatus(string $status, $endsAt): array
    {
        $client = User::factory()->create();
        $plan = SubscriptionPlan::query()->firstOrFail();

        $subscription = ClientSubscription::unguarded(fn () => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'status' => $status,
            'next_run_at' => now()->addDay(),
            'ends_at' => $endsAt,
            'auto_renew' => true,
        ]));

        Order::createForTesting([
            'client_id' => $client->id,
            'subscription_id' => $subscription->id,
            'origin' => Order::ORIGIN_CHECKOUT,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'Subscription address',
            'price' => 500,
        ]);

        return [$client, $subscription];
    }

    private function seedSecondClientAndSubscription(): array
    {
        $client = User::factory()->create();
        $plan = SubscriptionPlan::query()->firstOrFail();

        $subscription = ClientSubscription::unguarded(fn () => ClientSubscription::query()->create([
            'client_id' => $client->id,
            'subscription_plan_id' => $plan->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
            'next_run_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
            'auto_renew' => false,
        ]));

        return [$client, $subscription];
    }
}

