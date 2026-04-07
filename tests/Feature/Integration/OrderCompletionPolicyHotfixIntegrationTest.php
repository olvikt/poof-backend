<?php

namespace Tests\Feature\Integration;

use App\Actions\Orders\Completion\SubmitOrderCompletionByCourierAction;
use App\Actions\Orders\Completion\UploadOrderCompletionProofAction;
use App\Livewire\Client\OrderCreate;
use App\Livewire\Courier\MyOrders;
use App\Models\Order;
use App\Models\OrderCompletionProof;
use App\Models\OrderCompletionRequest;
use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCompletionPolicyHotfixIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_livewire_create_path_assigns_proof_policy_for_door_handover(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $this->actingAs($client, 'web');

        Livewire::test(OrderCreate::class)
            ->set('street', 'Тестова')
            ->set('house', '1')
            ->set('city', 'Дніпро')
            ->set('address_text', 'Тестова 1')
            ->set('lat', 48.4501)
            ->set('lng', 35.0302)
            ->set('coordsFromAddressBook', true)
            ->set('address_precision', 'exact')
            ->set('handover_type', Order::HANDOVER_DOOR)
            ->call('submit')
            ->call('declineSaveAddressAndContinue')
            ->assertSet('showPaymentModal', true);

        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'handover_type' => Order::HANDOVER_DOOR,
            'completion_policy' => Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM,
        ]);
    }

    public function test_livewire_create_path_assigns_none_policy_for_non_door_handover(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $this->actingAs($client, 'web');

        Livewire::test(OrderCreate::class)
            ->set('street', 'Тестова')
            ->set('house', '2')
            ->set('city', 'Дніпро')
            ->set('address_text', 'Тестова 2')
            ->set('lat', 48.4502)
            ->set('lng', 35.0303)
            ->set('coordsFromAddressBook', true)
            ->set('address_precision', 'exact')
            ->set('handover_type', Order::HANDOVER_HAND)
            ->call('submit')
            ->call('declineSaveAddressAndContinue')
            ->assertSet('showPaymentModal', true);

        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'handover_type' => Order::HANDOVER_HAND,
            'completion_policy' => Order::COMPLETION_POLICY_NONE,
        ]);
    }

    public function test_real_courier_livewire_path_cannot_finalize_proof_aware_order_without_required_photos(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);

        $order = $this->createDoorOrderViaLivewire($client);

        $order->forceFill([
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_IN_PROGRESS,
            'courier_id' => $courier->id,
            'started_at' => now(),
        ])->save();

        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->call('complete', $order->id);

        $order->refresh();

        $this->assertSame(Order::STATUS_IN_PROGRESS, $order->status);
        $this->assertDatabaseHas('order_completion_requests', [
            'order_id' => $order->id,
            'status' => OrderCompletionRequest::STATUS_DRAFT,
        ]);
    }

    public function test_real_client_api_path_exposes_proofs_and_supports_confirm_or_dispute_actions(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);
        $courier = User::factory()->create(['role' => User::ROLE_COURIER]);

        $orderForConfirm = $this->prepareAwaitingClientConfirmationOrder($client, $courier);

        Sanctum::actingAs($client);

        $this->getJson('/api/client/orders/'.$orderForConfirm->id.'/completion-proof')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.flags.can_confirm', true)
            ->assertJsonPath('data.flags.can_dispute', true)
            ->assertJsonCount(2, 'data.proofs');

        $this->postJson('/api/client/orders/'.$orderForConfirm->id.'/completion-proof/confirm')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('order_completion_requests', [
            'order_id' => $orderForConfirm->id,
            'status' => OrderCompletionRequest::STATUS_CLIENT_CONFIRMED,
        ]);

        $orderForDispute = $this->prepareAwaitingClientConfirmationOrder($client, $courier);

        $this->postJson('/api/client/orders/'.$orderForDispute->id.'/completion-proof/disputes', [
            'reason_code' => 'proof_mismatch',
            'comment' => 'Фото не відповідають очікуванню',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('order_completion_requests', [
            'order_id' => $orderForDispute->id,
            'status' => OrderCompletionRequest::STATUS_DISPUTED,
        ]);
    }

    public function test_canonical_api_contract_remains_stable_and_pending_order_not_visible_to_courier(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $address = ClientAddress::query()->create([
            'user_id' => $client->id,
            'title' => 'Дім',
            'address_text' => 'вул. API стабільність, 1',
            'lat' => 48.4572,
            'lng' => 35.0308,
            'is_default' => true,
        ]);

        Sanctum::actingAs($client);
        $response = $this->postJson('/api/orders', [
            'type' => 'one_time',
            'service' => 'trash_removal',
            'bags_count' => 2,
            'total_weight_kg' => 5.0,
            'scheduled_date' => '2026-04-07',
            'time_from' => '10:00',
            'time_to' => '12:00',
            'address_id' => $address->id,
        ]);

        $response->assertCreated()
            ->assertJsonMissingPath('order.handover_type')
            ->assertJsonMissingPath('order.order_type')
            ->assertJsonMissingPath('order.completion_policy')
            ->assertJsonPath('order.status', Order::STATUS_NEW)
            ->assertJsonPath('order.payment_status', Order::PAY_PENDING);

        $orderId = (int) $response->json('order.id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
        ]);

        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true]);
        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonCount(0, 'orders');
    }

    public function test_canonical_api_still_uses_default_address_when_address_id_missing(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $defaultAddress = ClientAddress::query()->create([
            'user_id' => $client->id,
            'title' => 'Дім',
            'address_text' => 'вул. За замовчуванням, 10',
            'lat' => 48.4600,
            'lng' => 35.0400,
            'is_default' => true,
        ]);

        Sanctum::actingAs($client);

        $response = $this->postJson('/api/orders', [
            'type' => 'one_time',
            'service' => 'trash_removal',
            'bags_count' => 1,
            'total_weight_kg' => 1.5,
            'scheduled_date' => '2026-04-07',
            'time_from' => '09:00',
            'time_to' => '10:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('order.address_id', $defaultAddress->id)
            ->assertJsonPath('order.address_text', $defaultAddress->address_text);
    }

    private function createDoorOrderViaLivewire(User $client): Order
    {
        $this->actingAs($client, 'web');

        Livewire::test(OrderCreate::class)
            ->set('street', 'Тестова')
            ->set('house', '10')
            ->set('city', 'Дніпро')
            ->set('address_text', 'Тестова 10')
            ->set('lat', 48.45)
            ->set('lng', 35.03)
            ->set('coordsFromAddressBook', true)
            ->set('address_precision', 'exact')
            ->set('handover_type', Order::HANDOVER_DOOR)
            ->call('submit')
            ->call('declineSaveAddressAndContinue');

        return Order::query()->where('client_id', $client->id)->latest('id')->firstOrFail();
    }

    private function prepareAwaitingClientConfirmationOrder(User $client, User $courier): Order
    {
        $order = $this->createDoorOrderViaLivewire($client);
        $order->forceFill([
            'payment_status' => Order::PAY_PAID,
            'status' => Order::STATUS_IN_PROGRESS,
            'courier_id' => $courier->id,
            'started_at' => now(),
        ])->save();

        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_DOOR_PHOTO, 'proofs/door.jpg', null, 'image/jpeg', 1024);
        app(UploadOrderCompletionProofAction::class)->handle($order, $courier, OrderCompletionProof::TYPE_CONTAINER_PHOTO, 'proofs/container.jpg', null, 'image/jpeg', 1024);
        app(SubmitOrderCompletionByCourierAction::class)->handle($order, $courier);

        return $order->fresh();
    }
}
