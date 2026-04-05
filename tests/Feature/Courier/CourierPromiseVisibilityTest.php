<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Livewire\Courier\AvailableOrders;
use App\Livewire\Courier\MyOrders;
use App\Livewire\Courier\OfferCard;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class CourierPromiseVisibilityTest extends TestCase
{
    public function test_expired_order_does_not_appear_in_available_orders(): void
    {
        $courier = User::factory()->create(['role' => 'courier', 'is_online' => true]);
        $client = User::factory()->create(['role' => 'client']);

        $staleOrder = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'service_mode' => Order::SERVICE_MODE_ASAP,
            'valid_until_at' => now()->subMinute(),
            'address_text' => 'вул. Стара, 1',
            'price' => 100,
        ]);

        $freshOrder = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'service_mode' => Order::SERVICE_MODE_ASAP,
            'valid_until_at' => now()->addHour(),
            'address_text' => 'вул. Нова, 2',
            'price' => 100,
        ]);

        OrderOffer::query()->create([
            'order_id' => $staleOrder->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->addMinute(),
        ]);

        OrderOffer::query()->create([
            'order_id' => $freshOrder->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->addMinute(),
        ]);

        Livewire::actingAs($courier)
            ->test(AvailableOrders::class)
            ->assertViewHas('orders', function ($orders) use ($staleOrder, $freshOrder): bool {
                $ids = $orders->pluck('id')->all();

                return in_array($freshOrder->id, $ids, true) && ! in_array($staleOrder->id, $ids, true);
            });
    }

    public function test_offer_card_shows_valid_until_meta(): void
    {
        $courier = User::factory()->create(['role' => 'courier', 'is_online' => true]);
        $client = User::factory()->create(['role' => 'client']);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'service_mode' => Order::SERVICE_MODE_ASAP,
            'valid_until_at' => now()->addMinutes(10),
            'address_text' => 'вул. Термінова, 7',
            'price' => 120,
        ]);

        OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'type' => OrderOffer::TYPE_PRIMARY,
            'sequence' => 1,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->addMinute(),
        ]);

        Livewire::actingAs($courier)
            ->test(OfferCard::class)
            ->call('loadOffer')
            ->assertSee('Активне до')
            ->assertSee('Терміново');
    }

    public function test_active_my_orders_card_keeps_promise_metadata_visible_after_accept(): void
    {
        $courier = User::factory()->create(['role' => 'courier', 'is_online' => true]);
        $client = User::factory()->create(['role' => 'client']);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
            'service_mode' => Order::SERVICE_MODE_PREFERRED_WINDOW,
            'window_from_at' => now()->setTime(13, 0),
            'window_to_at' => now()->setTime(15, 0),
            'valid_until_at' => now()->addMinutes(20),
            'address_text' => 'вул. Активна, 3',
            'price' => 180,
            'accepted_at' => now()->subMinutes(5),
            'started_at' => now()->subMinutes(2),
        ]);

        Livewire::actingAs($courier)
            ->test(MyOrders::class)
            ->assertSee('Час виконання')
            ->assertSee(optional($order->window_from_at)->format('d.m.Y') ?? '')
            ->assertSee('Бажане вікно')
            ->assertSee('13:00–15:00')
            ->assertSee('Активне до')
            ->assertSee('Терміново');
    }

    public function test_active_my_orders_card_uses_neutral_label_for_unknown_service_mode(): void
    {
        $courier = User::factory()->create(['role' => 'courier', 'is_online' => true]);
        $client = User::factory()->create(['role' => 'client']);

        Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'service_mode' => 'custom_mode',
            'valid_until_at' => now()->addHour(),
            'address_text' => 'вул. Режимна, 12',
            'price' => 130,
            'accepted_at' => now()->subMinute(),
        ]);

        Livewire::actingAs($courier)
            ->test(MyOrders::class)
            ->assertSee('Час виконання')
            ->assertSee('Інший режим');
    }
}
