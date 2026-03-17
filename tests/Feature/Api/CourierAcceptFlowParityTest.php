<?php

namespace Tests\Feature\Api;

use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourierAcceptFlowParityTest extends TestCase
{
    public function test_api_refusal_matches_web_domain_result_for_already_taken_order(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $webCourier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => false,
        ]);

        $apiCourier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
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
}
