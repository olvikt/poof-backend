<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Livewire\Courier\MyOrders;
use App\Models\Courier;
use App\Models\CourierEarning;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierMyOrdersTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_orders_and_statistics_tabs(): void
    {
        [$courier, $client] = $this->createCourierAndClient();
        $this->createActiveOrder($courier, $client, 'вул. Активна 1');
        $this->createDoneOrderWithEarning($courier, $client, now()->setTime(10, 0), 120);

        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->assertSee('Мої замовлення')
            ->assertSee('Статистика')
            ->assertSee('Замовлення #')
            ->assertDontSee('Виконані замовлення')
            ->call('setActiveTab', 'stats')
            ->assertSet('activeTab', 'stats')
            ->assertSee('Виконані замовлення')
            ->assertDontSee('Замовлення #');
    }

    public function test_tab_switch_and_accordion_state_persist_across_refresh(): void
    {
        [$courier, $client] = $this->createCourierAndClient();

        $todayOrder = $this->createDoneOrderWithEarning($courier, $client, now()->setTime(11, 30), 150);
        $olderDate = now()->subDay()->setTime(9, 15);
        $this->createDoneOrderWithEarning($courier, $client, $olderDate, 90);

        $this->actingAs($courier, 'web');

        $component = Livewire::test(MyOrders::class)
            ->call('setActiveTab', 'stats')
            ->assertSet('activeTab', 'stats')
            ->assertSee('Виконані замовлення');

        $component
            ->call('toggleCompletedStatDate', now()->toDateString())
            ->assertSet('expandedCompletedStatDates', [])
            ->call('$refresh')
            ->assertSet('activeTab', 'stats')
            ->assertSet('expandedCompletedStatDates', []);

        $component
            ->call('toggleCompletedStatDate', $olderDate->toDateString())
            ->assertSet('expandedCompletedStatDates', [$olderDate->toDateString()])
            ->call('$refresh')
            ->assertSet('activeTab', 'stats')
            ->assertSet('expandedCompletedStatDates', [$olderDate->toDateString()]);

        $this->assertDatabaseHas('orders', ['id' => $todayOrder->id, 'status' => Order::STATUS_DONE]);
    }

    private function createCourierAndClient(): array
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
            'is_busy' => true,
            'session_state' => User::SESSION_IN_PROGRESS,
        ]);

        Courier::query()->firstOrCreate(['user_id' => $courier->id], ['status' => Courier::STATUS_DELIVERING]);

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        return [$courier, $client];
    }

    private function createActiveOrder(User $courier, User $client, string $address): Order
    {
        return Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
            'address_text' => $address,
            'price' => 200,
            'started_at' => now()->subMinutes(15),
            'accepted_at' => now()->subMinutes(20),
            'handover_type' => Order::HANDOVER_DOOR,
            'completion_policy' => Order::COMPLETION_POLICY_DOOR_TWO_PHOTO_CLIENT_CONFIRM,
        ]);
    }

    private function createDoneOrderWithEarning(User $courier, User $client, \Carbon\CarbonInterface $completedAt, int $amount): Order
    {
        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Статистика '.(string) $completedAt->timestamp,
            'price' => $amount,
            'completed_at' => $completedAt,
        ]);

        CourierEarning::query()->create([
            'courier_id' => $courier->id,
            'order_id' => $order->id,
            'gross_amount' => $amount,
            'commission_rate_percent' => 0,
            'commission_amount' => 0,
            'net_amount' => $amount,
            'bonuses_amount' => 0,
            'penalties_amount' => 0,
            'adjustments_amount' => 0,
            'earning_status' => CourierEarning::STATUS_SETTLED,
            'settled_at' => $completedAt,
        ]);

        return $order;
    }
}
