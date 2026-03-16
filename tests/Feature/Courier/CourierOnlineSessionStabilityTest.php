<?php

namespace Tests\Feature\Courier;

use App\Jobs\MarkInactiveCouriers;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierOnlineSessionStabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_session_survives_switching_between_courier_tabs(): void
    {
        $courier = $this->createCourier();

        $courier->goOnline();

        $this->actingAs($courier, 'web')->get(route('courier.my-orders'))->assertOk();
        $this->actingAs($courier, 'web')->get(route('courier.orders'))->assertOk();
        $this->actingAs($courier, 'web')->get(route('courier.my-orders'))->assertOk();

        $courier->refresh();

        $this->assertTrue($courier->isCourierOnline());
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
    }

    public function test_fresh_location_timestamp_keeps_courier_online_during_inactive_check(): void
    {
        $courier = $this->createCourier();

        $courier->goOnline();
        $courier->courierProfile()->update([
            'last_location_at' => now()->subSeconds(30),
        ]);

        (new MarkInactiveCouriers())->handle();

        $courier->refresh();

        $this->assertTrue($courier->isCourierOnline());
        $this->assertTrue((bool) $courier->is_online);
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
    }

    public function test_ttl_marks_courier_offline_without_fresh_location(): void
    {
        $courier = $this->createCourier();

        $courier->goOnline();
        $courier->courierProfile()->update([
            'last_location_at' => now()->subSeconds(121),
        ]);

        (new MarkInactiveCouriers())->handle();

        $courier->refresh();

        $this->assertFalse($courier->isCourierOnline());
        $this->assertFalse((bool) $courier->is_online);
        $this->assertSame(Courier::STATUS_OFFLINE, $courier->courierProfile->status);
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
