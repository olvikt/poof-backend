<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Actions\Orders\Completion\ConfirmOrderCompletionByClientAction;
use App\Actions\Orders\Completion\StartOrderCompletionProofAction;
use App\Actions\Orders\Completion\SubmitOrderCompletionByCourierAction;
use App\Actions\Orders\Completion\UploadOrderCompletionProofAction;
use App\Actions\Orders\Lifecycle\CompleteOrderByCourierAction;
use App\Models\Courier;
use App\Models\CourierEarning;
use App\Models\Order;
use App\Models\OrderCompletionProof;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCompletionProofFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_door_order_happy_path_requires_proofs_then_client_confirmation_then_done(): void
    {
        [$courier, $order] = $this->createInProgressPaidOrder(Order::HANDOVER_DOOR);

        $request = app(StartOrderCompletionProofAction::class)->handle($order, $courier);
        $this->assertNotNull($request);

        $this->assertTrue(app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door-1.jpg'));
        $this->assertTrue(app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'proofs/container-1.jpg'));
        $this->assertTrue(app(SubmitOrderCompletionByCourierAction::class)->handle($order, $courier));

        $request = $request->fresh();
        $this->assertSame(OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION, $request->status);
        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->fresh()->status);

        $this->assertTrue(app(ConfirmOrderCompletionByClientAction::class)->handle($order));
        $this->assertSame(Order::STATUS_DONE, $order->fresh()->status);
        $this->assertDatabaseHas('courier_earnings', ['order_id' => $order->id]);
    }

    public function test_submit_fails_without_door_photo(): void
    {
        [$courier, $order] = $this->createInProgressPaidOrder(Order::HANDOVER_DOOR);

        app(StartOrderCompletionProofAction::class)->handle($order, $courier);
        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'proofs/container.jpg');

        $this->assertFalse(app(SubmitOrderCompletionByCourierAction::class)->handle($order, $courier));
    }

    public function test_submit_fails_without_container_photo(): void
    {
        [$courier, $order] = $this->createInProgressPaidOrder(Order::HANDOVER_DOOR);

        app(StartOrderCompletionProofAction::class)->handle($order, $courier);
        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door.jpg');

        $this->assertFalse(app(SubmitOrderCompletionByCourierAction::class)->handle($order, $courier));
    }

    public function test_foreign_courier_cannot_upload_proof(): void
    {
        [$courier, $order] = $this->createInProgressPaidOrder(Order::HANDOVER_DOOR);
        $foreignCourier = $this->makeCourier();

        $this->assertFalse(app(UploadOrderCompletionProofAction::class)->handle($order, $foreignCourier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door.jpg'));

        $this->assertDatabaseCount('order_completion_proofs', 0);
        $this->assertDatabaseCount('order_completion_requests', 0);
    }

    public function test_duplicate_proof_type_upload_updates_existing_record_deterministically(): void
    {
        [$courier, $order] = $this->createInProgressPaidOrder(Order::HANDOVER_DOOR);

        $this->assertTrue(app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door-v1.jpg'));
        $this->assertTrue(app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door-v2.jpg'));

        $this->assertDatabaseCount('order_completion_proofs', 1);
        $this->assertDatabaseHas('order_completion_proofs', [
            'order_id' => $order->id,
            'proof_type' => OrderCompletionProof::TYPE_DOOR_PHOTO,
            'file_path' => 'proofs/door-v2.jpg',
        ]);
    }

    public function test_duplicate_submit_is_idempotent(): void
    {
        [$courier, $order] = $this->createInProgressPaidOrder(Order::HANDOVER_DOOR);

        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door.jpg');
        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'proofs/container.jpg');

        $this->assertTrue(app(SubmitOrderCompletionByCourierAction::class)->handle($order, $courier));
        $firstSubmittedAt = OrderCompletionRequest::query()->where('order_id', $order->id)->value('submitted_at');

        $this->assertTrue(app(SubmitOrderCompletionByCourierAction::class)->handle($order, $courier));
        $secondSubmittedAt = OrderCompletionRequest::query()->where('order_id', $order->id)->value('submitted_at');

        $this->assertSame((string) $firstSubmittedAt, (string) $secondSubmittedAt);
    }

    public function test_duplicate_confirm_does_not_duplicate_finalization_or_settlement(): void
    {
        [$courier, $order] = $this->createInProgressPaidOrder(Order::HANDOVER_DOOR);

        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door.jpg');
        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'proofs/container.jpg');
        app(SubmitOrderCompletionByCourierAction::class)->handle($order, $courier);

        $confirmAction = app(ConfirmOrderCompletionByClientAction::class);
        $this->assertTrue($confirmAction->handle($order));
        $this->assertTrue($confirmAction->handle($order));

        $this->assertSame(1, CourierEarning::query()->where('order_id', $order->id)->count());
    }

    public function test_same_order_cannot_create_duplicate_active_completion_request(): void
    {
        [$courier, $order] = $this->createInProgressPaidOrder(Order::HANDOVER_DOOR);

        $action = app(StartOrderCompletionProofAction::class);
        $first = $action->handle($order, $courier);
        $second = $action->handle($order, $courier);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('order_completion_requests', 1);
    }

    public function test_settlement_not_triggered_on_submit_but_only_after_client_confirm_for_door_policy(): void
    {
        [$courier, $order] = $this->createInProgressPaidOrder(Order::HANDOVER_DOOR);

        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door.jpg');
        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'proofs/container.jpg');

        $this->assertTrue(app(SubmitOrderCompletionByCourierAction::class)->handle($order, $courier));
        $this->assertDatabaseCount('courier_earnings', 0);

        $this->assertTrue(app(ConfirmOrderCompletionByClientAction::class)->handle($order));
        $this->assertDatabaseCount('courier_earnings', 1);
    }

    public function test_legacy_non_door_order_completion_still_finalizes_immediately(): void
    {
        [$courier, $order] = $this->createInProgressPaidOrder(Order::HANDOVER_HAND);

        $this->assertTrue(app(CompleteOrderByCourierAction::class)->handle($order, $courier));

        $courier->refresh();
        $this->assertSame(Order::STATUS_DONE, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completed_at);
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
        $this->assertFalse((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_READY, $courier->session_state);
        $this->assertDatabaseCount('order_completion_requests', 0);
        $this->assertDatabaseCount('courier_earnings', 1);
    }

    public function test_door_order_complete_call_does_not_finalize_without_required_proofs(): void
    {
        [$courier, $order] = $this->createInProgressPaidOrder(Order::HANDOVER_DOOR);

        $this->assertFalse(app(CompleteOrderByCourierAction::class)->handle($order, $courier));

        $courier->refresh();
        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->fresh()->status);
        $this->assertSame(Courier::STATUS_DELIVERING, $courier->courierProfile->status);
        $this->assertTrue((bool) $courier->is_busy);
        $this->assertSame(User::SESSION_IN_PROGRESS, $courier->session_state);
        $this->assertDatabaseCount('courier_earnings', 0);
    }

    /** @return array{0:User,1:Order} */
    private function createInProgressPaidOrder(string $handoverType): array
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $courier = $this->makeCourier();

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
            'accepted_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(5),
            'address_text' => 'proof test address',
            'price' => 200,
            'handover_type' => $handoverType,
            'completion_policy' => $handoverType === Order::HANDOVER_DOOR
                ? Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM
                : Order::COMPLETION_POLICY_NONE,
        ]);

        return [$courier, $order];
    }

    private function makeCourier(): User
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'is_busy' => true,
            'session_state' => User::SESSION_ASSIGNED,
        ]);

        Courier::query()->firstOrCreate(
            ['user_id' => $courier->id],
            ['status' => Courier::STATUS_ASSIGNED]
        );

        return $courier;
    }
}
