<?php

namespace Tests\Feature\Api;

use App\Models\ClientAddress;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsOrderRuntimeFixtures;
use Tests\TestCase;

class ApiProtectedRoutesAuthTest extends TestCase
{
    use RefreshDatabase;
    use BuildsOrderRuntimeFixtures;

    public function test_guest_is_denied_for_each_sanctum_protected_route(): void
    {
        $order = $this->createDispatchableSearchingPaidOrder($this->createClient(), [
            'address_text' => 'вул. Гостьова, 1',
            'price' => 100,
        ]);

        $this->getJson('/api/client/profile')->assertUnauthorized();
        $this->putJson('/api/client/profile', [
            'name' => 'Guest',
            'push_notifications' => true,
            'email_notifications' => true,
        ])->assertUnauthorized();
        $this->getJson('/api/client/addresses')->assertUnauthorized();
        $this->postJson('/api/client/addresses', $this->addressPayload())->assertUnauthorized();
        $this->postJson('/api/orders', $this->orderPayload())->assertUnauthorized();
        $this->getJson('/api/orders/available')->assertUnauthorized();
        $this->postJson("/api/orders/{$order->id}/accept")->assertUnauthorized();
    }

    public function test_client_can_access_client_sanctum_routes(): void
    {
        $client = $this->createClient();

        $address = ClientAddress::query()->create([
            'user_id' => $client->id,
            'title' => 'Дім',
            'address_text' => 'вул. Клієнтська, 10',
            'city' => 'Dnipro',
            'street' => 'Клієнтська',
            'house' => '10',
            'lat' => 48.4501,
            'lng' => 35.0001,
            'is_default' => true,
        ]);

        Sanctum::actingAs($client);

        $this->getJson('/api/client/profile')
            ->assertOk()
            ->assertJsonPath('profile.user_id', $client->id);

        $this->putJson('/api/client/profile', [
            'name' => 'Updated Client',
            'push_notifications' => false,
            'email_notifications' => true,
        ])
            ->assertOk()
            ->assertJsonPath('profile.name', 'Updated Client')
            ->assertJsonPath('profile.push_notifications', false)
            ->assertJsonPath('profile.email_notifications', true);

        $this->getJson('/api/client/addresses')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $address->id);

        $this->postJson('/api/client/addresses', $this->addressPayload())
            ->assertCreated()
            ->assertJsonPath('address.city', 'Dnipro')
            ->assertJsonPath('address.street', 'Тестова');
    }

    public function test_courier_can_access_courier_sanctum_routes(): void
    {
        $client = $this->createClient();
        $courier = $this->createCourier();

        $searchingOrder = $this->createDispatchableSearchingPaidOrder($client, [
            'address_text' => 'вул. Доступна, 5',
            'price' => 135,
        ]);

        OrderOffer::createPrimaryPending($searchingOrder->id, $courier->id, 120);

        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $searchingOrder->id);

        $this->postJson("/api/orders/{$searchingOrder->id}/accept")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.id', $searchingOrder->id)
            ->assertJsonPath('order.courier_id', $courier->id);

        $courier->refresh();

        $this->assertSame(Courier::STATUS_ASSIGNED, $courier->courierProfile->status);
        $this->assertSame(User::SESSION_ASSIGNED, $courier->session_state);
    }

    public function test_courier_available_orders_endpoint_hides_searching_orders_without_pending_offer(): void
    {
        $client = $this->createClient();
        $courier = $this->createCourier();

        $this->createDispatchableSearchingPaidOrder($client, [
            'address_text' => 'вул. Невидима, 6',
            'price' => 140,
        ]);

        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available')
            ->assertOk()
            ->assertJsonCount(0, 'orders');
    }

    public function test_client_is_forbidden_from_courier_only_sanctum_routes(): void
    {
        $client = $this->createClient();
        $order = $this->createDispatchableSearchingPaidOrder($client, [
            'address_text' => 'вул. Курʼєрська, 7',
            'price' => 115,
        ]);

        Sanctum::actingAs($client);

        $this->getJson('/api/orders/available')->assertForbidden();
        $this->postJson("/api/orders/{$order->id}/accept")->assertForbidden();
    }

    public function test_busy_courier_gets_business_error_when_accepting_second_order_via_api(): void
    {
        $client = $this->createClient();
        $courier = $this->createCourier();

        $firstOrder = $this->createDispatchableSearchingPaidOrder($client, [
            'address_text' => 'вул. Перша API, 1',
            'price' => 111,
        ]);
        $secondOrder = $this->createDispatchableSearchingPaidOrder($client, [
            'address_text' => 'вул. Друга API, 2',
            'price' => 222,
        ]);

        Sanctum::actingAs($courier);

        $this->postJson("/api/orders/{$firstOrder->id}/accept")
            ->assertOk()
            ->assertJsonPath('success', true);

        // Drift mirrors deliberately: canonical guard must still refuse by active-order truth.
        $courier->forceFill([
            'is_online' => false,
            'is_busy' => false,
            'session_state' => User::SESSION_OFFLINE,
        ])->save();

        $this->postJson("/api/orders/{$secondOrder->id}/accept")
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Неможливо прийняти замовлення');

        $secondOrder->refresh();
        $this->assertSame(Order::STATUS_SEARCHING, $secondOrder->status);
        $this->assertNull($secondOrder->courier_id);
    }

    public function test_courier_can_access_courier_sanctum_routes_even_with_stale_runtime_mirrors(): void
    {
        $courier = $this->createCourier([
            'is_online' => false,
            'is_busy' => true,
            'session_state' => User::SESSION_OFFLINE,
        ]);

        Sanctum::actingAs($courier);

        $this->getJson('/api/orders/available')->assertOk();
    }

    public function test_client_is_forbidden_from_courier_routes_even_if_legacy_runtime_flags_look_courier_like(): void
    {
        $client = $this->createClient([
            'is_online' => true,
            'is_busy' => true,
            'session_state' => User::SESSION_ASSIGNED,
        ]);
        $order = $this->createDispatchableSearchingPaidOrder($client, [
            'address_text' => 'вул. Заборонена, 9',
            'price' => 101,
        ]);

        Sanctum::actingAs($client);

        $this->getJson('/api/orders/available')->assertForbidden();
        $this->postJson("/api/orders/{$order->id}/accept")->assertForbidden();
    }

    public function test_courier_is_forbidden_from_client_only_sanctum_routes(): void
    {
        $courier = $this->createCourier();

        Sanctum::actingAs($courier);

        $this->getJson('/api/client/profile')->assertForbidden();
        $this->putJson('/api/client/profile', [
            'name' => 'Courier',
            'push_notifications' => true,
            'email_notifications' => false,
        ])->assertForbidden();
        $this->getJson('/api/client/addresses')->assertForbidden();
        $this->postJson('/api/client/addresses', $this->addressPayload())->assertForbidden();
        $this->postJson('/api/orders', [
            ...$this->orderPayload(),
            'address_id' => 1,
        ])->assertForbidden();
    }

    private function createClient(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ], $attributes));
    }

    private function createCourier(array $attributes = []): User
    {
        $courier = User::factory()->create(array_merge([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => false,
        ], $attributes));

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
        ]);

        return $courier;
    }

    private function addressPayload(): array
    {
        return [
            'city' => 'Dnipro',
            'street' => 'Тестова',
            'house' => '11',
            'entrance' => '2',
            'floor' => '5',
            'apartment' => '21',
            'intercom' => '210',
            'lat' => 48.4601,
            'lng' => 35.0202,
        ];
    }

    private function orderPayload(): array
    {
        return [
            'type' => 'one_time',
            'service' => 'trash_removal',
            'bags_count' => 2,
            'total_weight_kg' => 5.4,
            'scheduled_date' => '2026-03-01',
            'time_from' => '10:00',
            'time_to' => '12:00',
        ];
    }
}
