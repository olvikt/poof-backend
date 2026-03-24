<?php

namespace Tests\Feature\Api;

use App\Models\Courier;
use App\Models\Order;
use App\Livewire\Courier\OfferCard;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
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

        $order = Order::createForTesting([
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


    public function test_livewire_offer_accept_matches_domain_refusal_semantics_for_busy_courier(): void
    {
        $client = $this->createUser(User::ROLE_CLIENT);

        $courier = $this->createUser(User::ROLE_COURIER, [
            'is_busy' => false,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_ONLINE,
        ]);

        $activeOrder = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address' => 'вул. Активна, 1',
            'address_text' => 'вул. Активна, 1',
            'price' => 150,
        ]);

        $offeredOrder = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address' => 'вул. Оферна, 2',
            'address_text' => 'вул. Оферна, 2',
            'price' => 180,
        ]);

        $offer = OrderOffer::query()->create([
            'order_id' => $offeredOrder->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->addMinute(),
        ]);

        $this->assertTrue($activeOrder->acceptBy($courier));

        $this->actingAs($courier, 'web');

        Livewire::test(OfferCard::class)
            ->set('offer', $offer)
            ->call('accept')
            ->assertDispatched('notify', type: 'error', message: 'Не вдалося прийняти');

        $offeredOrder->refresh();
        $offer->refresh();
        $courier->refresh();

        $this->assertSame(Order::STATUS_SEARCHING, $offeredOrder->status);
        $this->assertNull($offeredOrder->courier_id);
        $this->assertSame(OrderOffer::STATUS_PENDING, $offer->status);
        $this->assertTrue($courier->isBusyForAccept());
    }

    public function test_livewire_offer_accept_and_api_accept_share_same_final_domain_result(): void
    {
        $client = $this->createUser(User::ROLE_CLIENT);

        $livewireCourier = $this->createUser(User::ROLE_COURIER, [
            'is_busy' => false,
        ]);

        $apiCourier = $this->createUser(User::ROLE_COURIER, [
            'is_busy' => false,
        ]);

        Courier::query()->create([
            'user_id' => $livewireCourier->id,
            'status' => Courier::STATUS_ONLINE,
        ]);

        Courier::query()->create([
            'user_id' => $apiCourier->id,
            'status' => Courier::STATUS_ONLINE,
        ]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address' => 'вул. Паритетна, 1',
            'address_text' => 'вул. Паритетна, 1',
            'price' => 100,
        ]);

        $offer = OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $livewireCourier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->addMinute(),
        ]);

        $this->actingAs($livewireCourier, 'web');

        Livewire::test(OfferCard::class)
            ->set('offer', $offer)
            ->call('accept')
            ->assertRedirect(route('courier.my-orders'));

        Sanctum::actingAs($apiCourier);

        $apiRefusal = $this->postJson('/api/orders/' . $order->id . '/accept');

        $apiRefusal
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Неможливо прийняти замовлення');

        $order->refresh();
        $offer->refresh();
        $livewireCourier->refresh();
        $apiCourier->refresh();

        $this->assertSame(Order::STATUS_ACCEPTED, $order->status);
        $this->assertSame($livewireCourier->id, $order->courier_id);
        $this->assertSame(OrderOffer::STATUS_ACCEPTED, $offer->status);
        $this->assertTrue((bool) $livewireCourier->is_busy);
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
