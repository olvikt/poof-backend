<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Actions\Orders\Completion\CreateOrderCompletionDisputeAction;
use App\Actions\Orders\Completion\StartOrderCompletionProofAction;
use App\Actions\Orders\Completion\SubmitOrderCompletionByCourierAction;
use App\Actions\Orders\Completion\UploadOrderCompletionProofAction;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderCompletionProof;
use App\Models\OrderOffer;
use App\Models\User;
use App\Services\Dispatch\OfferDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferDispatcherRuntimeBlockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_allows_courier_after_submit_awaiting_client_confirmation(): void
    {
        [$courier, $activeOrder] = $this->createInProgressDoorOrder();
        $newOrder = $this->createSearchingOrder($activeOrder->client_id);

        app(StartOrderCompletionProofAction::class)->handle($activeOrder, $courier);
        app(UploadOrderCompletionProofAction::class)->handle($activeOrder, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door.jpg');
        app(UploadOrderCompletionProofAction::class)->handle($activeOrder, $courier, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'proofs/container.jpg');
        $this->assertTrue(app(SubmitOrderCompletionByCourierAction::class)->handle($activeOrder, $courier));

        $offer = app(OfferDispatcher::class)->dispatchForOrder($newOrder->fresh(), 'test_submit_release');

        $this->assertNotNull($offer);
        $this->assertSame($courier->id, $offer->courier_id);
        $this->assertSame(OrderOffer::STATUS_PENDING, $offer->status);
    }

    public function test_dispatch_allows_courier_after_dispute_status(): void
    {
        [$courier, $activeOrder] = $this->createInProgressDoorOrder();
        $newOrder = $this->createSearchingOrder($activeOrder->client_id);

        app(StartOrderCompletionProofAction::class)->handle($activeOrder, $courier);
        app(UploadOrderCompletionProofAction::class)->handle($activeOrder, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door.jpg');
        app(UploadOrderCompletionProofAction::class)->handle($activeOrder, $courier, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'proofs/container.jpg');
        $this->assertTrue(app(SubmitOrderCompletionByCourierAction::class)->handle($activeOrder, $courier));

        $client = User::query()->findOrFail($activeOrder->client_id);
        $this->assertTrue(app(CreateOrderCompletionDisputeAction::class)->handle($activeOrder, $client, 'not_my_bag', 'dispute in dispatcher test'));

        $offer = app(OfferDispatcher::class)->dispatchForOrder($newOrder->fresh(), 'test_dispute_release');

        $this->assertNotNull($offer);
        $this->assertSame($courier->id, $offer->courier_id);
        $this->assertSame(OrderOffer::STATUS_PENDING, $offer->status);
    }

    public function test_dispatch_still_blocks_courier_with_accepted_order(): void
    {
        [$courier, $acceptedOrder] = $this->createAcceptedOrder();
        $newOrder = $this->createSearchingOrder($acceptedOrder->client_id);

        $offer = app(OfferDispatcher::class)->dispatchForOrder($newOrder->fresh(), 'test_accepted_blocks');

        $this->assertNull($offer);
        $this->assertDatabaseMissing('order_offers', [
            'order_id' => $newOrder->id,
            'courier_id' => $courier->id,
            'status' => OrderOffer::STATUS_PENDING,
        ]);
    }

    public function test_dispatch_still_blocks_courier_with_regular_in_progress_without_submitted_proof(): void
    {
        [$courier, $activeOrder] = $this->createInProgressDoorOrder();
        $newOrder = $this->createSearchingOrder($activeOrder->client_id);

        $offer = app(OfferDispatcher::class)->dispatchForOrder($newOrder->fresh(), 'test_in_progress_blocks');

        $this->assertNull($offer);
        $this->assertDatabaseMissing('order_offers', [
            'order_id' => $newOrder->id,
            'courier_id' => $courier->id,
            'status' => OrderOffer::STATUS_PENDING,
        ]);
    }

    private function createCourier(): User
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'is_busy' => true,
            'session_state' => User::SESSION_IN_PROGRESS,
            'last_lat' => 50.4501,
            'last_lng' => 30.5234,
            'last_seen_at' => now(),
        ]);

        Courier::query()->firstOrCreate(
            ['user_id' => $courier->id],
            ['status' => Courier::STATUS_ONLINE, 'last_location_at' => now()]
        );

        return $courier;
    }

    private function createSearchingOrder(int $clientId): Order
    {
        return Order::createForTesting([
            'client_id' => $clientId,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'dispatch candidate order',
            'price' => 190,
            'lat' => 50.4502,
            'lng' => 30.5235,
        ]);
    }

    /** @return array{0:User,1:Order} */
    private function createAcceptedOrder(): array
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createCourier();

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'accepted_at' => now()->subMinutes(2),
            'address_text' => 'accepted active order',
            'price' => 210,
        ]);

        return [$courier, $order];
    }

    /** @return array{0:User,1:Order} */
    private function createInProgressDoorOrder(): array
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = $this->createCourier();

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
            'accepted_at' => now()->subMinutes(8),
            'started_at' => now()->subMinutes(5),
            'address_text' => 'in progress active order',
            'price' => 230,
            'handover_type' => Order::HANDOVER_DOOR,
            'completion_policy' => Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM,
        ]);

        return [$courier, $order];
    }
}
