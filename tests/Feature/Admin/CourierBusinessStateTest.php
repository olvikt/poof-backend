<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\PoofStats;
use App\Jobs\MarkInactiveCouriers;
use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierBusinessStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_busy_courier_with_active_order_is_counted_as_busy_and_not_available_in_stats(): void
    {
        [$busyCourier, $freeCourier] = $this->seedBusyAndFreeCouriers();

        $stats = new class extends PoofStats {
            public function exposeStats(): array
            {
                return $this->getStats();
            }
        };

        $payload = collect($stats->exposeStats())
            ->mapWithKeys(fn ($stat) => [$stat->getLabel() => $stat->getValue()]);

        $this->assertSame(1, Courier::busy()->count());
        $this->assertSame(1, Courier::available()->count());
        $this->assertSame(1, (int) $payload['Занятые курьеры']);
        $this->assertSame(1, (int) $payload['Свободные курьеры']);

        $this->assertSame(Courier::STATUS_ASSIGNED, $busyCourier->courierProfile->status);
        $this->assertSame(Courier::STATUS_ONLINE, $freeCourier->courierProfile->status);
    }

    public function test_mark_inactive_does_not_drop_business_busy_courier_to_offline(): void
    {
        [$busyCourier, $freeCourier] = $this->seedBusyAndFreeCouriers();

        (new MarkInactiveCouriers())->handle();

        $busyCourier->refresh();
        $freeCourier->refresh();

        $this->assertSame(Courier::STATUS_ASSIGNED, $busyCourier->courierProfile->status);
        $this->assertSame(Courier::STATUS_OFFLINE, $freeCourier->courierProfile->status);
        $this->assertSame(1, Courier::busy()->count());
        $this->assertSame(0, Courier::available()->count());
    }

    public function test_map_may_hide_stale_courier_but_business_busy_counter_stays_correct(): void
    {
        [$busyCourier] = $this->seedBusyAndFreeCouriers();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'web')->getJson('/api/admin/map-data');

        $response->assertOk();
        $response->assertJsonCount(0, 'couriers');

        $this->assertSame(1, Courier::busy()->count());
        $this->assertTrue((bool) $busyCourier->is_busy);
    }

    private function seedBusyAndFreeCouriers(): array
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $busyCourier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => true,
            'last_lat' => 50.4501,
            'last_lng' => 30.5234,
        ]);

        Courier::query()->create([
            'user_id' => $busyCourier->id,
            'status' => Courier::STATUS_ASSIGNED,
            'last_location_at' => now()->subMinutes(5),
        ]);

        Order::query()->create([
            'client_id' => $client->id,
            'courier_id' => $busyCourier->id,
            'status' => Order::STATUS_ACCEPTED,
            'payment_status' => Order::PAY_PAID,
            'address_text' => 'вул. Бізнес, 1',
            'price' => 100,
        ]);

        $freeCourier = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_busy' => false,
            'last_lat' => 50.4502,
            'last_lng' => 30.5235,
        ]);

        Courier::query()->create([
            'user_id' => $freeCourier->id,
            'status' => Courier::STATUS_ONLINE,
            'last_location_at' => now()->subMinutes(5),
        ]);

        return [$busyCourier, $freeCourier];
    }
}
