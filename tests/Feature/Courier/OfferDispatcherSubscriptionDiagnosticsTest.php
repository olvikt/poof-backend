<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Models\Courier;
use App\Models\ClientSubscription;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Dispatch\DispatchDiagnosticReason;
use App\Services\Dispatch\OfferDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OfferDispatcherSubscriptionDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_searching_subscription_with_future_dispatch_available_at_is_deferred(): void
    {
        Log::spy();
        $order = $this->createSubscriptionSearchingOrder([
            'dispatch_available_at' => now()->addMinutes(5),
        ]);

        $offer = app(OfferDispatcher::class)->dispatchForOrder($order, 'test_deferred');

        $this->assertNull($offer);
        $this->assertDatabaseCount('order_offers', 0);
        Log::shouldHaveReceived('debug')->withArgs(fn (string $event, array $ctx): bool => $event === 'dispatch_skipped'
            && ($ctx['reason'] ?? null) === DispatchDiagnosticReason::DISPATCH_DEFERRED_UNTIL
            && ($ctx['order_id'] ?? null) === $order->id)->atLeast()->once();
    }

    public function test_paid_searching_subscription_with_no_candidates_emits_no_candidates(): void
    {
        Log::spy();
        $order = $this->createSubscriptionSearchingOrder();

        $offer = app(OfferDispatcher::class)->dispatchForOrder($order, 'test_no_candidates');

        $this->assertNull($offer);
        Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $ctx): bool => $event === 'offer_not_created'
            && ($ctx['reason'] ?? null) === DispatchDiagnosticReason::NO_CANDIDATES
            && ($ctx['order_id'] ?? null) === $order->id)->atLeast()->once();
    }

    public function test_eligible_paid_searching_subscription_creates_one_offer(): void
    {
        $order = $this->createSubscriptionSearchingOrder();
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'last_lat' => 50.4501,
            'last_lng' => 30.5234,
        ]);
        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
            'is_verified' => true,
            'last_location_at' => now(),
        ]);

        $offer = app(OfferDispatcher::class)->dispatchForOrder($order, 'test_success');

        $this->assertNotNull($offer);
        $this->assertSame($order->id, $offer->order_id);
        $this->assertSame(OrderOffer::STATUS_PENDING, $offer->status);
        $this->assertDatabaseCount('order_offers', 1);
    }

    private function createSubscriptionSearchingOrder(array $override = []): Order
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $subscription = $this->createSubscription($client->id);

        return Order::createForTesting(array_merge([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'origin' => Order::ORIGIN_SUBSCRIPTION,
            'order_type' => Order::TYPE_SUBSCRIPTION,
            'subscription_id' => $subscription->id,
            'address_text' => 'subscription execution order',
            'lat' => 50.4502,
            'lng' => 30.5235,
            'price' => 200,
        ], $override));
    }


    private function createSubscription(int $clientId): ClientSubscription
    {
        $plan = SubscriptionPlan::factory()->create();

        return ClientSubscription::unguarded(fn (): ClientSubscription => ClientSubscription::query()->create([
            'client_id' => $clientId,
            'subscription_plan_id' => $plan->id,
            'status' => ClientSubscription::STATUS_ACTIVE,
                        'next_run_at' => now()->addDay(),
        ]));
    }

}
