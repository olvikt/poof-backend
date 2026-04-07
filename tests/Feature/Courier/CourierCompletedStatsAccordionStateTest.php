<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Livewire\Courier\MyOrders;
use App\Models\CourierEarning;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourierCompletedStatsAccordionStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_is_opened_only_once_by_default_on_initial_render(): void
    {
        [$courier, $client] = $this->createCourierAndClient();
        $today = now()->toDateString();

        $todayOrder = $this->createDoneOrder($courier, $client, 'вул. Сьогоднішня 1', now()->setTime(10, 0));
        $yesterdayOrder = $this->createDoneOrder($courier, $client, 'вул. Вчорашня 1', now()->subDay()->setTime(9, 0));
        $this->createEarning($courier, $todayOrder, 120);
        $this->createEarning($courier, $yesterdayOrder, 90);

        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->assertSet('expandedCompletedStatDates', [$today])
            ->call('$refresh')
            ->assertSet('expandedCompletedStatDates', [$today]);
    }

    public function test_manual_collapse_of_today_persists_across_refresh(): void
    {
        [$courier, $client] = $this->createCourierAndClient();
        $today = now()->toDateString();

        $todayOrder = $this->createDoneOrder($courier, $client, 'вул. Сьогоднішня 1', now()->setTime(11, 0));
        $this->createEarning($courier, $todayOrder, 150);

        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->assertSet('expandedCompletedStatDates', [$today])
            ->call('toggleCompletedStatDate', $today)
            ->assertSet('expandedCompletedStatDates', [])
            ->call('$refresh')
            ->assertSet('expandedCompletedStatDates', []);
    }

    public function test_manual_expansion_state_persists_across_refresh(): void
    {
        [$courier, $client] = $this->createCourierAndClient();

        $today = now()->toDateString();
        $olderDate = now()->subDay()->toDateString();

        $todayOrder = $this->createDoneOrder($courier, $client, 'вул. Сьогоднішня 1', now()->setTime(11, 0));
        $olderOrder = $this->createDoneOrder($courier, $client, 'вул. Старіша 2', now()->subDay()->setTime(12, 0));
        $this->createEarning($courier, $todayOrder, 140);
        $this->createEarning($courier, $olderOrder, 80);

        $this->actingAs($courier, 'web');

        Livewire::test(MyOrders::class)
            ->assertSet('expandedCompletedStatDates', [$today])
            ->call('toggleCompletedStatDate', $today)
            ->call('toggleCompletedStatDate', $olderDate)
            ->assertSet('expandedCompletedStatDates', [$olderDate])
            ->call('$refresh')
            ->assertSet('expandedCompletedStatDates', [$olderDate]);
    }

    public function test_pruned_dates_are_removed_without_reopening_other_sections(): void
    {
        [$courier, $client] = $this->createCourierAndClient();

        $today = now()->toDateString();
        $olderDate = now()->subDay()->toDateString();

        $todayOrder = $this->createDoneOrder($courier, $client, 'вул. Сьогоднішня 1', now()->setTime(9, 0));
        $olderOrder = $this->createDoneOrder($courier, $client, 'вул. Старіша 2', now()->subDay()->setTime(18, 0));
        $this->createEarning($courier, $todayOrder, 100);
        $this->createEarning($courier, $olderOrder, 70);

        $this->actingAs($courier, 'web');

        $component = Livewire::test(MyOrders::class)
            ->assertSet('expandedCompletedStatDates', [$today])
            ->call('toggleCompletedStatDate', $today)
            ->call('toggleCompletedStatDate', $olderDate)
            ->assertSet('expandedCompletedStatDates', [$olderDate]);

        CourierEarning::query()->where('order_id', $olderOrder->id)->delete();
        $olderOrder->delete();

        $component
            ->call('$refresh')
            ->assertSet('expandedCompletedStatDates', []);
    }

    private function createCourierAndClient(): array
    {
        $courier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => true,
        ]);

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        return [$courier, $client];
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
