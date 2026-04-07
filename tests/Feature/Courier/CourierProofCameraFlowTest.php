<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Livewire\Courier\MyOrders;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderCompletionProof;
use App\Models\OrderCompletionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CourierProofCameraFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_proof_aware_order_renders_camera_cards_and_hides_plain_upload_actions(): void
    {
        [$courier, $order] = $this->createInProgressDoorOrder();
        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->assertSee('Фото у двері')
            ->assertSee('Фото у контейнера')
            ->assertDontSee('Завантажити');
    }

    public function test_proof_aware_completion_requires_both_proofs_before_confirmation_modal_opens(): void
    {
        Storage::fake('public');
        [$courier, $order] = $this->createInProgressDoorOrder();
        $this->actingAs($courier, 'web');

        $component = Livewire::test(MyOrders::class)
            ->call('requestCompletionConfirmation', $order->id)
            ->assertSet('completionConfirmationOrderId', null);

        $component
            ->set('doorProofFiles.'.$order->id, UploadedFile::fake()->image('door.jpg'))
            ->call('uploadProof', $order->id, OrderCompletionProof::TYPE_DOOR_PHOTO, 'camera', now()->toIso8601String())
            ->call('requestCompletionConfirmation', $order->id)
            ->assertSet('completionConfirmationOrderId', null)
            ->set('containerProofFiles.'.$order->id, UploadedFile::fake()->image('container.jpg'))
            ->call('uploadProof', $order->id, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'file_fallback', now()->toIso8601String())
            ->call('requestCompletionConfirmation', $order->id)
            ->assertSet('completionConfirmationOrderId', $order->id);
    }

    public function test_confirm_completion_modal_submits_to_awaiting_client_confirmation(): void
    {
        Storage::fake('public');
        [$courier, $order] = $this->createInProgressDoorOrder();
        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->set('doorProofFiles.'.$order->id, UploadedFile::fake()->image('door.jpg'))
            ->call('uploadProof', $order->id, OrderCompletionProof::TYPE_DOOR_PHOTO, 'camera', now()->toIso8601String())
            ->set('containerProofFiles.'.$order->id, UploadedFile::fake()->image('container.jpg'))
            ->call('uploadProof', $order->id, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'camera', now()->toIso8601String())
            ->call('requestCompletionConfirmation', $order->id)
            ->call('confirmCompletion')
            ->assertSet('completionConfirmationOrderId', null);

        $this->assertDatabaseHas('order_completion_requests', [
            'order_id' => $order->id,
            'status' => OrderCompletionRequest::STATUS_AWAITING_CLIENT_CONFIRMATION,
        ]);
    }

    public function test_retake_replaces_same_proof_type_and_tracks_metadata(): void
    {
        Storage::fake('public');
        [$courier, $order] = $this->createInProgressDoorOrder();
        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->set('doorProofFiles.'.$order->id, UploadedFile::fake()->image('door-v1.jpg'))
            ->call('uploadProof', $order->id, OrderCompletionProof::TYPE_DOOR_PHOTO, 'camera', '2026-04-07T10:00:00+00:00')
            ->set('doorProofFiles.'.$order->id, UploadedFile::fake()->image('door-v2.jpg'))
            ->call('uploadProof', $order->id, OrderCompletionProof::TYPE_DOOR_PHOTO, 'file_fallback', '2026-04-07T10:01:00+00:00');

        $this->assertDatabaseCount('order_completion_proofs', 1);
        $this->assertDatabaseHas('order_completion_proofs', [
            'order_id' => $order->id,
            'proof_type' => OrderCompletionProof::TYPE_DOOR_PHOTO,
            'captured_via' => 'file_fallback',
        ]);
    }

    private function createInProgressDoorOrder(): array
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'is_busy' => true,
            'session_state' => User::SESSION_IN_PROGRESS,
        ]);

        Courier::query()->firstOrCreate(['user_id' => $courier->id], ['status' => Courier::STATUS_DELIVERING]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
            'accepted_at' => now()->subMinutes(8),
            'started_at' => now()->subMinutes(4),
            'address_text' => 'вул. Камери, 1',
            'price' => 200,
            'handover_type' => Order::HANDOVER_DOOR,
            'completion_policy' => Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM,
        ]);

        return [$courier, $order];
    }
}
