<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\PoofStats;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoofStatsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_stats_match_business_rules_and_include_total_counts(): void
    {
        $clientA = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $clientB = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $onlineCourierUser = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true]);
        $busyCourierUser = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true]);

        Courier::query()->create([
            'user_id' => $onlineCourierUser->id,
            'status' => Courier::STATUS_ONLINE,
            'last_location_at' => now()->subMinutes(30),
        ]);

        Courier::query()->create([
            'user_id' => $busyCourierUser->id,
            'status' => Courier::STATUS_ASSIGNED,
            'last_location_at' => now()->subMinutes(30),
        ]);

        Order::createForTesting([
            'client_id' => $clientA->id,
            'status' => Order::STATUS_NEW,
            'payment_status' => Order::PAY_PENDING,
            'price' => 100,
            'address_text' => 'new order',
        ]);

        Order::createForTesting([
            'client_id' => $clientA->id,
            'courier_id' => $busyCourierUser->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'price' => 200,
            'address_text' => 'accepted order',
        ]);

        $donePaidToday = Order::createForTesting([
            'client_id' => $clientB->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'price' => 300,
            'address_text' => 'old paid order',
        ]);

        $paidYesterday = Order::createForTesting([
            'client_id' => $clientB->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'price' => 999,
            'address_text' => 'paid yesterday',
        ]);

        $donePaidToday->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()])->save();
        $paidYesterday->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDay()])->save();

        $stats = new class extends PoofStats {
            public function exposeStats(): array
            {
                return $this->getStats();
            }
        };

        $payload = collect($stats->exposeStats())
            ->mapWithKeys(fn ($stat) => [$stat->getLabel() => $stat->getValue()]);

        $this->assertSame(1, (int) $payload['Курьеры онлайн']);
        $this->assertSame(1, (int) $payload['Свободные курьеры']);
        $this->assertSame(1, (int) $payload['Занятые курьеры']);
        $this->assertSame(1, (int) $payload['Активные заказы']);
        $this->assertSame(2, (int) $payload['Заказы сегодня']);
        $this->assertSame('₴500', $payload['Доход сегодня']);
        $this->assertSame(2, (int) $payload['Всего клиентов']);
        $this->assertSame(2, (int) $payload['Всего курьеров']);
    }
}
