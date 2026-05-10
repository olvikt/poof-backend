<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Actions\Orders\Completion\SubmitOrderCompletionByCourierAction;
use App\Models\Order;
use App\Models\OrderCompletionProof;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use App\Notifications\OrderLifecyclePushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderCompletionLifecyclePhase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_proof_submit_creates_audit_event_and_notifies_client(): void
    {
        Notification::fake();
        [$order, $client, $courier] = $this->makeAwaitingFlow();

        $this->assertTrue(app(SubmitOrderCompletionByCourierAction::class)->handle($order, $courier));

        $this->assertDatabaseHas('order_completion_events', [
            'order_id' => $order->id,
            'event_type' => 'proof_submitted',
            'actor_type' => 'courier',
        ]);
        Notification::assertSentTo($client, OrderLifecyclePushNotification::class);
    }

    public function test_auto_complete_notifies_courier_and_creates_event(): void
    {
        Notification::fake();
        [$order, , $courier] = $this->makeAwaitingFlow();
        app(SubmitOrderCompletionByCourierAction::class)->handle($order, $courier);

        OrderCompletionRequest::query()->where('order_id', $order->id)->update(['auto_confirmation_due_at' => now()->subMinute()]);
        Artisan::call('orders:auto-complete-awaiting-confirmation --limit=100');

        $this->assertDatabaseHas('order_completion_events', ['order_id' => $order->id, 'event_type' => 'auto_completed']);
        Notification::assertSentTo($courier, OrderLifecyclePushNotification::class);
    }

    private function makeAwaitingFlow(): array
    {
        $client = User::factory()->create();
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);
        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
            'order_type' => 'one_time',
            'bags_count' => 2,
            'price' => 100,
            'client_charge_amount' => 100,
            'courier_payout_amount' => 80,
            'system_subsidy_amount' => 0,
            'funding_source' => 'client',
            'origin' => 'checkout',
            'address_text' => 'addr',
            'lat' => 50.45,
            'lng' => 30.52,
            'completion_policy' => Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM,
        ]);
        app(\App\Actions\Orders\Completion\StartOrderCompletionProofAction::class)->handle($order, $courier);
        app(\App\Actions\Orders\Completion\UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door.jpg');
        app(\App\Actions\Orders\Completion\UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'proofs/container.jpg');

        return [$order->fresh(), $client, $courier];
    }
}
