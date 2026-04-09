<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Livewire\Courier\MyOrders;
use App\Models\Courier;
use App\Models\CourierEarning;
use App\Models\Order;
use App\Models\User;
use App\Services\Courier\Earnings\CourierCompletedOrdersDailyStatsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CourierMyOrdersHotColdReadPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_tab_hot_render_does_not_query_completed_stats_source(): void
    {
        [$courier, $client] = $this->createCourierAndClient();
        $this->createActiveOrder($courier, $client);
        $this->createDoneOrderWithEarning($courier, $client);

        $this->actingAs($courier, 'web');

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(MyOrders::class)
            ->assertSet('activeTab', 'orders')
            ->assertSee('Замовлення #');

        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        $this->assertStringNotContainsString('courier_earnings', $queries);
    }

    public function test_stats_tab_loads_independently_after_switching_tabs(): void
    {
        [$courier, $client] = $this->createCourierAndClient();
        $this->createActiveOrder($courier, $client);
        $this->createDoneOrderWithEarning($courier, $client);

        $this->actingAs($courier, 'web');

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(MyOrders::class)
            ->assertSet('activeTab', 'orders')
            ->call('setActiveTab', 'stats')
            ->assertSet('activeTab', 'stats')
            ->assertSee('Виконані замовлення');

        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        $this->assertStringContainsString('courier_earnings', $queries);
    }

    public function test_cold_stats_failure_does_not_break_hot_runtime_orders_pane(): void
    {
        [$courier, $client] = $this->createCourierAndClient();
        $activeOrder = $this->createActiveOrder($courier, $client);

        $this->actingAs($courier, 'web');

        $failingQuery = Mockery::mock(CourierCompletedOrdersDailyStatsQuery::class);
        $failingQuery->shouldReceive('forCourier')->andThrow(new \RuntimeException('stats source unavailable'));
        $this->app->instance(CourierCompletedOrdersDailyStatsQuery::class, $failingQuery);

        Livewire::test(MyOrders::class)
            ->assertSee('Замовлення #'.$activeOrder->id)
            ->assertSet('statsPaneUnavailable', false)
            ->call('setActiveTab', 'stats')
            ->assertSet('statsPaneUnavailable', true)
            ->assertSee('Статистика тимчасово недоступна')
            ->call('setActiveTab', 'orders')
            ->assertSet('activeTab', 'orders')
            ->assertSee('Замовлення #'.$activeOrder->id);
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

    private function createActiveOrder(User $courier, User $client): Order
    {
        return Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Гаряча 1',
            'price' => 200,
            'started_at' => now()->subMinutes(15),
            'accepted_at' => now()->subMinutes(20),
        ]);
    }

    private function createDoneOrderWithEarning(User $courier, User $client): Order
    {
        $completedAt = now()->subHour();

        $order = Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Холодна '.$completedAt->timestamp,
            'price' => 160,
            'completed_at' => $completedAt,
        ]);

        CourierEarning::query()->create([
            'courier_id' => $courier->id,
            'order_id' => $order->id,
            'gross_amount' => 160,
            'commission_rate_percent' => 0,
            'commission_amount' => 0,
            'net_amount' => 160,
            'bonuses_amount' => 0,
            'penalties_amount' => 0,
            'adjustments_amount' => 0,
            'earning_status' => CourierEarning::STATUS_SETTLED,
            'settled_at' => $completedAt,
        ]);

        return $order;
    }
}
