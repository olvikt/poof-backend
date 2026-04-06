<?php

namespace Tests\Feature\Courier;

use App\Livewire\Courier\AvailableOrders;
use App\Livewire\Courier\MyOrders;
use App\Livewire\Courier\OnlineToggle;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierBusyUxFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_busy_courier_with_accepted_order_sees_active_order_block_and_start_action(): void
    {
        [$courier, $activeOrder] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED);
        $this->createPendingOfferForCourier($courier);

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertSet('online', true)
            ->assertSee('Активне замовлення')
            ->assertSee('#' . $activeOrder->id)
            ->assertSee('Завершіть його, щоб отримати нове')
            ->assertSee('Перейти →', false)
            ->assertDontSee('Пошук замовлень...');

        Livewire::test(MyOrders::class)
            ->assertSet('online', true)
            ->assertSee('#' . $activeOrder->id)
            ->assertSee('▶️ Почати виконання', false)
            ->assertDontSee('Ви не на лінії');
    }

    public function test_busy_courier_with_in_progress_order_sees_active_order_block_and_complete_action(): void
    {
        [$courier, $activeOrder] = $this->createCourierWithActiveOrder(Order::STATUS_IN_PROGRESS);
        $this->createPendingOfferForCourier($courier);

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertSet('online', true)
            ->assertSee('Активне замовлення')
            ->assertSee('#' . $activeOrder->id)
            ->assertSee('Завершіть його, щоб отримати нове')
            ->assertSee('Перейти →', false)
            ->assertDontSee('Пошук замовлень...');

        Livewire::test(MyOrders::class)
            ->assertSet('online', true)
            ->assertSee('#' . $activeOrder->id)
            ->assertSee('✅ Завершити замовлення', false)
            ->assertDontSee('Ви не на лінії');
    }

    public function test_busy_courier_toggle_uses_working_busy_semantics_instead_of_offline_label(): void
    {
        [$courier] = $this->createCourierWithActiveOrder(Order::STATUS_ACCEPTED);

        $this->actingAs($courier, 'web');

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', true)
            ->assertSet('busyWithActiveOrder', true)
            ->assertSee('На лінії', false)
            ->assertDontSee('Завершіть активне замовлення, щоб змінити статус.', false)
            ->call('toggleOnlineState')
            ->assertDispatched('courier-online-toggled', online: true, changed: false, reason: 'blocked_by_active_order')
            ->assertDispatched('courier-online-toggle-blocked', reason: 'blocked_by_active_order')
            ->assertSet('online', true);
    }

    public function test_free_courier_keeps_default_online_offline_flow_and_offer_search_state(): void
    {
        $courier = $this->createCourier();

        $this->actingAs($courier, 'web');

        Livewire::test(AvailableOrders::class)
            ->assertSet('online', false)
            ->assertSee('Ви не на лінії');

        Livewire::test(OnlineToggle::class)
            ->assertSet('online', false)
            ->assertSet('busyWithActiveOrder', false)
            ->assertSee('Не на лінії', false)
            ->call('toggleOnlineState')
            ->assertSet('online', true);

        Livewire::test(AvailableOrders::class)
            ->assertSet('online', true)
            ->assertSee('Пошук замовлень...')
            ->assertDontSee('Активне замовлення');
    }

    private function createPendingOfferForCourier(User $courier): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Вільна, 8',
            'price' => 150,
        ]);

        OrderOffer::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => OrderOffer::STATUS_PENDING,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    private function createCourierWithActiveOrder(string $orderStatus): array
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $courier = $this->createCourier();
        $courier->goOnline();

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => $orderStatus,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Активна, 11',
            'price' => 100,
            'accepted_at' => now(),
            'started_at' => $orderStatus === Order::STATUS_IN_PROGRESS ? now() : null,
        ]);

        $courier->refresh();

        return [$courier, $order];
    }

    private function createCourier(): User
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => false,
            'is_online' => false,
        ]);

        Courier::query()->create([
            'user_id' => $courier->id,
            'status' => Courier::STATUS_OFFLINE,
            'last_location_at' => null,
        ]);

        return $courier;
    }
}
