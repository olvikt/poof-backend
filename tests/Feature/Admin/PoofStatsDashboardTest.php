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

    public function test_dashboard_stats_follow_admin_business_rules_and_internal_consistency(): void
    {
        $clientA = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        $clientB = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $freeCourierUser = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true]);
        $busyCourierUser = User::factory()->create(['role' => User::ROLE_COURIER, 'is_active' => true]);

        Courier::query()->create([
            'user_id' => $freeCourierUser->id,
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

        $completedToday = Order::createForTesting([
            'client_id' => $clientB->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'price' => 300,
            'address_text' => 'completed today',
            'completed_at' => now(),
        ]);

        $completedYesterday = Order::createForTesting([
            'client_id' => $clientB->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'price' => 999,
            'address_text' => 'completed yesterday',
            'completed_at' => now()->subDay(),
        ]);

        $completedToday->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()])->save();
        $completedYesterday->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()])->save();

        $payload = $this->dashboardPayload();

        $this->assertSame(2, (int) $payload['Курьеры онлайн']);
        $this->assertSame(1, (int) $payload['Свободные курьеры']);
        $this->assertSame(1, (int) $payload['Занятые курьеры']);
        $this->assertSame(
            (int) $payload['Курьеры онлайн'],
            (int) $payload['Свободные курьеры'] + (int) $payload['Занятые курьеры'],
        );

        $this->assertSame(1, (int) $payload['Активные заказы']);
        $this->assertSame(2, (int) $payload['Заказы сегодня']);
        $this->assertSame('₴300', $payload['Доход сегодня (завершено)']);
        $this->assertSame(2, (int) $payload['Всего клиентов']);
        $this->assertSame(2, (int) $payload['Всего курьеров']);
    }

    public function test_income_today_uses_completed_at_not_created_or_updated_timestamps(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $mustBeIncluded = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'price' => 111,
            'address_text' => 'included completed today',
            'completed_at' => now()->subHour(),
        ]);

        $excludedCreatedTodayButCompletedYesterday = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'price' => 222,
            'address_text' => 'created today but completed yesterday',
            'completed_at' => now()->subDay(),
        ]);

        $excludedUpdatedTodayButCompletedYesterday = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_DONE,
            'payment_status' => Order::PAY_PAID,
            'price' => 333,
            'address_text' => 'updated today but completed yesterday',
            'completed_at' => now()->subDays(2),
        ]);

        $excludedNotDone = Order::createForTesting([
            'client_id' => $client->id,
            'status' => Order::STATUS_SEARCHING,
            'payment_status' => Order::PAY_PAID,
            'price' => 444,
            'address_text' => 'paid but not completed',
        ]);

        $mustBeIncluded->forceFill([
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(3),
        ])->save();

        $excludedCreatedTodayButCompletedYesterday->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $excludedUpdatedTodayButCompletedYesterday->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now(),
        ])->save();

        $excludedNotDone->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $payload = $this->dashboardPayload();

        $this->assertSame('₴111', $payload['Доход сегодня (завершено)']);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardPayload(): array
    {
        $stats = new class extends PoofStats {
            public function exposeStats(): array
            {
                return $this->getStats();
            }
        };

        return collect($stats->exposeStats())
            ->mapWithKeys(fn ($stat) => [$stat->getLabel() => $stat->getValue()])
            ->all();
    }
}
