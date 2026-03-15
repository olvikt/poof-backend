<?php

namespace Tests\Feature\Api;

use App\Jobs\DispatchOrderJob;
use App\Models\ClientAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_create_order_with_full_canonical_payload(): void
    {
        Bus::fake();

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $address = ClientAddress::query()->create([
            'user_id' => $client->id,
            'title' => 'Дім',
            'address_text' => 'вул. Тестова, 1',
            'lat' => 48.4572,
            'lng' => 35.0308,
            'is_default' => true,
        ]);

        Sanctum::actingAs($client);

        $payload = [
            'type' => 'one_time',
            'service' => 'trash_removal',
            'bags_count' => 3,
            'total_weight_kg' => 7.5,
            'scheduled_date' => '2026-02-15',
            'time_from' => '10:00',
            'time_to' => '12:00',
            'address_id' => $address->id,
            'comment' => 'Пакети біля дверей',
        ];

        $response = $this->postJson('/api/orders', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.type', 'one_time')
            ->assertJsonPath('order.service', 'trash_removal')
            ->assertJsonPath('order.bags_count', 3)
            ->assertJsonPath('order.total_weight_kg', 7.5)
            ->assertJsonPath('order.time_from', '10:00:00')
            ->assertJsonPath('order.time_to', '12:00:00')
            ->assertJsonPath('order.address_id', $address->id)
            ->assertJsonPath('order.address_text', 'вул. Тестова, 1');

        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'status' => 'new',
            'payment_status' => 'pending',
            'type' => 'one_time',
            'service' => 'trash_removal',
            'bags_count' => 3,
            'total_weight_kg' => 7.5,
            'currency' => 'UAH',
            'address_id' => $address->id,
            'address_text' => 'вул. Тестова, 1',
            'scheduled_date' => '2026-02-15',
            'time_from' => '10:00:00',
            'time_to' => '12:00:00',
            'comment' => 'Пакети біля дверей',
        ]);

        Bus::assertDispatched(DispatchOrderJob::class);
    }

    public function test_store_response_contract_uses_canonical_fields_and_excludes_legacy_aliases(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $address = ClientAddress::query()->create([
            'user_id' => $client->id,
            'title' => 'Офіс',
            'address_text' => 'пр. Перемоги, 99',
            'lat' => 50.4501,
            'lng' => 30.5234,
            'is_default' => true,
        ]);

        Sanctum::actingAs($client);

        $response = $this->postJson('/api/orders', [
            'type' => 'subscription',
            'service' => 'trash_removal',
            'bags_count' => 2,
            'total_weight_kg' => 4.2,
            'scheduled_date' => '2026-03-01',
            'time_from' => '14:00',
            'time_to' => '16:00',
            'address_id' => $address->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'success',
                'order' => [
                    'id',
                    'client_id',
                    'status',
                    'payment_status',
                    'type',
                    'service',
                    'bags_count',
                    'total_weight_kg',
                    'price',
                    'currency',
                    'address_id',
                    'address_text',
                    'lat',
                    'lng',
                    'scheduled_date',
                    'time_from',
                    'time_to',
                    'comment',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonMissingPath('order.order_type')
            ->assertJsonMissingPath('order.scheduled_time_from')
            ->assertJsonMissingPath('order.scheduled_time_to')
            ->assertJsonMissingPath('order.handover_type');
    }


    public function test_store_rejects_foreign_address_id(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $otherClient = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $foreignAddress = ClientAddress::query()->create([
            'user_id' => $otherClient->id,
            'address_text' => 'вул. Чужа, 1',
            'lat' => 50.4547,
            'lng' => 30.5238,
            'is_default' => true,
        ]);

        Sanctum::actingAs($client);

        $response = $this->postJson('/api/orders', [
            'type' => 'one_time',
            'service' => 'trash_removal',
            'bags_count' => 2,
            'total_weight_kg' => 4,
            'scheduled_date' => '2026-03-01',
            'time_from' => '10:00',
            'time_to' => '12:00',
            'address_id' => $foreignAddress->id,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address_id']);
    }

    public function test_store_uses_default_address_when_address_id_missing(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $defaultAddress = ClientAddress::query()->create([
            'user_id' => $client->id,
            'title' => 'Дім',
            'address_text' => 'вул. Основна, 10',
            'lat' => 48.46,
            'lng' => 35.04,
            'is_default' => true,
        ]);

        Sanctum::actingAs($client);

        $response = $this->postJson('/api/orders', [
            'type' => 'one_time',
            'service' => 'trash_removal',
            'bags_count' => 1,
            'total_weight_kg' => 1.5,
            'scheduled_date' => '2026-03-01',
            'time_from' => '09:00',
            'time_to' => '10:00',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('order.address_id', $defaultAddress->id)
            ->assertJsonPath('order.address_text', 'вул. Основна, 10');

        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'address_id' => $defaultAddress->id,
            'address_text' => 'вул. Основна, 10',
        ]);
    }

    public function test_store_returns_422_when_address_id_missing_and_default_address_absent(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        Sanctum::actingAs($client);

        $response = $this->postJson('/api/orders', [
            'type' => 'one_time',
            'service' => 'trash_removal',
            'bags_count' => 1,
            'total_weight_kg' => 1.5,
            'scheduled_date' => '2026-03-01',
            'time_from' => '09:00',
            'time_to' => '10:00',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Адрес не знайдено');
    }


    public function test_store_without_address_id_uses_only_default_address_not_arbitrary_user_address(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        ClientAddress::query()->create([
            'user_id' => $client->id,
            'title' => 'Не default',
            'address_text' => 'вул. Недефолтна, 20',
            'lat' => 48.41,
            'lng' => 35.01,
            'is_default' => false,
        ]);

        $defaultAddress = ClientAddress::query()->create([
            'user_id' => $client->id,
            'title' => 'Default',
            'address_text' => 'вул. Дефолтна, 21',
            'lat' => 48.42,
            'lng' => 35.02,
            'is_default' => true,
        ]);

        Sanctum::actingAs($client);

        $response = $this->postJson('/api/orders', [
            'type' => 'one_time',
            'service' => 'trash_removal',
            'bags_count' => 1,
            'total_weight_kg' => 1.5,
            'scheduled_date' => '2026-03-01',
            'time_from' => '09:00',
            'time_to' => '10:00',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('order.address_id', $defaultAddress->id)
            ->assertJsonPath('order.address_text', 'вул. Дефолтна, 21');
    }

    public function test_store_without_address_id_and_without_default_returns_422_even_if_non_default_exists(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        ClientAddress::query()->create([
            'user_id' => $client->id,
            'title' => 'Тільки недефолтний',
            'address_text' => 'вул. Єдина, 5',
            'lat' => 48.5,
            'lng' => 35.1,
            'is_default' => false,
        ]);

        Sanctum::actingAs($client);

        $response = $this->postJson('/api/orders', [
            'type' => 'one_time',
            'service' => 'trash_removal',
            'bags_count' => 1,
            'total_weight_kg' => 1.5,
            'scheduled_date' => '2026-03-01',
            'time_from' => '09:00',
            'time_to' => '10:00',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Адрес не знайдено');
    }

    public function test_new_pending_order_created_via_api_is_not_visible_in_courier_available_until_payment_transition(): void
    {
        Bus::fake();

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $defaultAddress = ClientAddress::query()->create([
            'user_id' => $client->id,
            'address_text' => 'вул. Клієнтська, 3',
            'lat' => 48.45,
            'lng' => 35.03,
            'is_default' => true,
        ]);

        Sanctum::actingAs($client);

        $createResponse = $this->postJson('/api/orders', [
            'type' => 'one_time',
            'service' => 'trash_removal',
            'bags_count' => 1,
            'total_weight_kg' => 1,
            'scheduled_date' => '2026-03-01',
            'time_from' => '10:00',
            'time_to' => '11:00',
            'address_id' => $defaultAddress->id,
        ])->assertCreated();

        $this->assertSame('new', $createResponse->json('order.status'));
        $this->assertSame('pending', $createResponse->json('order.payment_status'));

        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonPath('orders', []);
    }

    public function test_store_validates_required_fields_and_rejects_legacy_payload_fields(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        Sanctum::actingAs($client);

        $response = $this->postJson('/api/orders', [
            'service' => 'trash_removal',
            'scheduled_date' => '2026-03-01',
            'time_from' => '10:00',
            'time_to' => '09:00',
            'order_type' => 'one_time',
            'scheduled_time_from' => '10:00',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type',
                'bags_count',
                'total_weight_kg',
                'time_to',
                'order_type',
                'scheduled_time_from',
            ]);
    }

    public function test_legacy_order_type_without_type_is_rejected_to_prevent_mismatched_contract_regression(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $address = ClientAddress::query()->create([
            'user_id' => $client->id,
            'address_text' => 'вул. Центральна, 7',
            'lat' => 49.8397,
            'lng' => 24.0297,
            'is_default' => true,
        ]);

        Sanctum::actingAs($client);

        $response = $this->postJson('/api/orders', [
            'order_type' => 'subscription',
            'service' => 'trash_removal',
            'bags_count' => 1,
            'total_weight_kg' => 2,
            'scheduled_date' => '2026-03-01',
            'time_from' => '10:00',
            'time_to' => '11:00',
            'address_id' => $address->id,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'order_type']);
    }
}
