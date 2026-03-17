<?php

namespace Tests\Feature\Api;

use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourierAcceptFlowParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_refusal_matches_web_domain_result_for_already_taken_order(): void
    {
        $client = $this->createUser(User::ROLE_CLIENT);

        $webCourier = $this->createUser(User::ROLE_COURIER, [
            'is_busy' => false,
        ]);

        $apiCourier = $this->createUser(User::ROLE_COURIER, [
            'is_busy' => false,
        ]);

        Courier::query()->create([
            'user_id' => $webCourier->id,
            'status' => Courier::STATUS_ONLINE,
        ]);

        Courier::query()->create([
            'user_id' => $apiCourier->id,
            'status' => Courier::STATUS_ONLINE,
        ]);

        $order = Order::query()->create([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address' => 'вул. Єдина, 1',
            'address_text' => 'вул. Єдина, 1',
            'price' => 100,
        ]);

        $webAccept = $this->actingAs($webCourier, 'web')
            ->post(route('courier.orders.accept', $order));

        $webAccept
            ->assertRedirect(route('courier.my-orders'))
            ->assertSessionHas('success', 'Замовлення прийнято.');

        Sanctum::actingAs($apiCourier);

        $apiRefusal = $this->postJson('/api/orders/' . $order->id . '/accept');

        $apiRefusal
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Неможливо прийняти замовлення');

        $order->refresh();
        $webCourier->refresh();
        $apiCourier->refresh();

        $this->assertSame(Order::STATUS_ACCEPTED, $order->status);
        $this->assertSame($webCourier->id, $order->courier_id);
        $this->assertTrue((bool) $webCourier->is_busy);
        $this->assertFalse((bool) $apiCourier->is_busy);
    }

    private function createUser(string $role, array $attributes = []): User
    {
        static $counter = 0;
        $counter++;

        $suffix = Str::uuid()->toString();

        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
            'email' => $role . '-' . $suffix . '@example.test',
            'phone' => '+380' . str_pad((string) $counter, 9, '0', STR_PAD_LEFT),
        ], $attributes));
    }
}
