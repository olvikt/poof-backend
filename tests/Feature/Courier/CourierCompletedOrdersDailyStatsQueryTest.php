<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Models\CourierEarning;
use App\Models\Order;
use App\Models\User;
use App\Services\Courier\Earnings\CourierCompletedOrdersDailyStatsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierCompletedOrdersDailyStatsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_completed_orders_by_date_with_day_totals_and_rows(): void
    {
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true]);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        [$todayOrder1, $todayOrder2, $yesterdayOrder] = [
            $this->createDoneOrder($courier, $client, 'проспект Героїв 11', now()->setTime(14, 25)),
            $this->createDoneOrder($courier, $client, 'вул. Перемоги 5', now()->setTime(16, 10)),
            $this->createDoneOrder($courier, $client, 'вул. Вчорашня 1', now()->subDay()->setTime(9, 15)),
        ];

        $this->createEarning($courier, $todayOrder1, 200);
        $this->createEarning($courier, $todayOrder2, 150);
        $this->createEarning($courier, $yesterdayOrder, 90);

        $stats = app(CourierCompletedOrdersDailyStatsQuery::class)->forCourier($courier, 7);

        $this->assertCount(2, $stats);
        $this->assertSame('Сьогодні', $stats[0]['label']);
        $this->assertSame(350, $stats[0]['total_amount']);
        $this->assertSame('350 ₴', $stats[0]['total_amount_formatted']);
        $this->assertSame('проспект Героїв 11', $stats[0]['orders'][1]['address_text']);
        $this->assertSame('14:25', $stats[0]['orders'][1]['completed_time']);
        $this->assertSame('200 ₴', $stats[0]['orders'][1]['amount_formatted']);

        $this->assertSame(now()->subDay()->toDateString(), $stats[1]['date']);
        $this->assertSame(90, $stats[1]['total_amount']);
    }

    public function test_returns_empty_state_data_when_courier_has_no_completed_orders(): void
    {
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true]);

        $stats = app(CourierCompletedOrdersDailyStatsQuery::class)->forCourier($courier, 7);

        $this->assertTrue($stats->isEmpty());
    }

    public function test_respects_limit_window(): void
    {
        $courier = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true]);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $insideWindow = $this->createDoneOrder($courier, $client, 'вул. Нова 10', now()->subDays(1));
        $outsideWindow = $this->createDoneOrder($courier, $client, 'вул. Стара 20', now()->subDays(20));

        $this->createEarning($courier, $insideWindow, 120);
        $this->createEarning($courier, $outsideWindow, 450);

        $stats = app(CourierCompletedOrdersDailyStatsQuery::class)->forCourier($courier, 7);

        $this->assertCount(1, $stats);
        $this->assertSame('вул. Нова 10', $stats[0]['orders'][0]['address_text']);
    }

    private function createDoneOrder(User $courier, User $client, string $address, \Carbon\CarbonInterface $completedAt): Order
    {
        return Order::createForTesting([
            'client_id' => $client->id,
            'courier_id' => $courier->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'address_text' => $address,
            'price' => 200,
            'completed_at' => $completedAt,
        ]);
    }

    private function createEarning(User $courier, Order $order, int $amount): void
    {
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
            'settled_at' => $order->completed_at,
        ]);
    }
}
