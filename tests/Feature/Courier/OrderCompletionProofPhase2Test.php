<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Actions\Orders\Completion\Admin\ResolveOrderCompletionDisputeAction;
use App\Actions\Orders\Completion\CreateOrderCompletionDisputeAction;
use App\Actions\Orders\Completion\SubmitOrderCompletionByCourierAction;
use App\Actions\Orders\Completion\UploadOrderCompletionProofAction;
use App\Models\Courier;
use App\Models\CourierEarning;
use App\Models\Order;
use App\Models\OrderCompletionDispute;
use App\Models\OrderCompletionProof;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCompletionProofPhase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_auto_confirm_command_finalizes_due_request_once(): void
    {
        [$client, $courier, $order] = $this->createInProgressPaidDoorOrder();

        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door.jpg', null, 'image/jpeg', 1024);
        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'proofs/container.jpg', null, 'image/jpeg', 1024);
        app(SubmitOrderCompletionByCourierAction::class)->handle($order, $courier);

        OrderCompletionRequest::query()->where('order_id', $order->id)->update(['auto_confirmation_due_at' => now()->subMinute()]);

        Artisan::call('orders:completion-proof:auto-confirm --limit=100');
        Artisan::call('orders:completion-proof:auto-confirm --limit=100');

        $this->assertSame(Order::STATUS_DONE, $order->fresh()->status);
        $this->assertSame(OrderCompletionRequest::STATUS_AUTO_CONFIRMED, $order->completionRequest->fresh()->status);
        $this->assertSame(1, CourierEarning::query()->where('order_id', $order->id)->count());
    }

    public function test_dispute_open_then_admin_resolve_confirmed_finalizes_once(): void
    {
        [$client, $courier, $order] = $this->createAwaitingClientConfirmationOrder();

        $this->assertTrue(app(CreateOrderCompletionDisputeAction::class)->handle($order, $client, 'not_my_bag', 'broken'));
        $this->assertFalse(app(CreateOrderCompletionDisputeAction::class)->handle($order, $client, 'not_my_bag', 'duplicate'));

        $dispute = OrderCompletionDispute::query()->where('order_id', $order->id)->firstOrFail();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);

        $action = app(ResolveOrderCompletionDisputeAction::class);
        $this->assertTrue($action->handle($dispute, $admin, true, 'validated'));
        $this->assertTrue($action->handle($dispute->fresh(), $admin, true, 'duplicate'));

        $this->assertSame(Order::STATUS_DONE, $order->fresh()->status);
        $this->assertSame(1, CourierEarning::query()->where('order_id', $order->id)->count());
    }

    public function test_dispute_resolve_rejected_keeps_order_not_done_without_settlement(): void
    {
        [$client, $courier, $order] = $this->createAwaitingClientConfirmationOrder();

        $this->assertTrue(app(CreateOrderCompletionDisputeAction::class)->handle($order, $client, 'wrong_location', 'reject'));

        $dispute = OrderCompletionDispute::query()->where('order_id', $order->id)->firstOrFail();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $this->assertTrue(app(ResolveOrderCompletionDisputeAction::class)->handle($dispute, $admin, false, 'retry'));

        $this->assertNotSame(Order::STATUS_DONE, $order->fresh()->status);
        $this->assertSame(0, CourierEarning::query()->where('order_id', $order->id)->count());
    }

    public function test_client_payload_endpoint_returns_safe_urls_and_auth_guards(): void
    {
        [$client, $courier, $order] = $this->createAwaitingClientConfirmationOrder();

        Sanctum::actingAs($client);
        $response = $this->getJson('/api/client/orders/'.$order->id.'/completion-proof');
        $response->assertOk()->assertJsonPath('success', true);
        $proofUrl = (string) $response->json('data.proofs.0.url');
        $this->assertNotSame('proofs/door.jpg', $proofUrl);
        $this->assertNotSame('', $proofUrl);

        $courierUser = User::query()->findOrFail($courier->id);
        Sanctum::actingAs($courierUser);
        $this->postJson('/api/client/orders/'.$order->id.'/completion-proof/confirm')->assertForbidden();
    }

    private function createAwaitingClientConfirmationOrder(): array
    {
        [$client, $courier, $order] = $this->createInProgressPaidDoorOrder();
        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door.jpg', null, 'image/jpeg', 1024);
        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'proofs/container.jpg', null, 'image/jpeg', 1024);
        app(SubmitOrderCompletionByCourierAction::class)->handle($order, $courier);

        return [$client, $courier, $order];
    }

    private function createInProgressPaidDoorOrder(): array
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
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
            'handover_type' => Order::HANDOVER_DOOR,
            'completion_policy' => Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM,
        ]);

        return [$client, $courier, $order];
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

        Courier::query()->firstOrCreate(['user_id' => $courier->id], ['status' => Courier::STATUS_ASSIGNED]);

        return $courier;
    }
}
