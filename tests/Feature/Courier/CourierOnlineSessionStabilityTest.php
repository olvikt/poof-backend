<?php

namespace Tests\Feature\Courier;

use App\Jobs\MarkInactiveCouriers;
use App\Livewire\Courier\LocationTracker;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
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

    public function test_heartbeat_with_accuracy_110_is_accepted_and_prevents_premature_offline(): void
    {
        config()->set('courier_runtime.heartbeat.max_accuracy_meters', 120.0);

        $courier = $this->createCourier();
        $courier->goOnline();

        $this->actingAs($courier, 'web');

        Livewire::test(LocationTracker::class)
            ->call('updateLocation', 48.4647, 35.0462, 110);

        $courier->refresh();

        $this->assertNotNull($courier->courierProfile->last_location_at);
        $this->assertTrue((bool) $courier->is_online);

        (new MarkInactiveCouriers())->handle();

        $courier->refresh();

        $this->assertTrue($courier->isCourierOnline());
        $this->assertSame(Courier::STATUS_ONLINE, $courier->courierProfile->status);
    }

    public function test_stale_sweeper_logs_forced_offline_reason(): void
    {
        Log::spy();

        $courier = $this->createCourier();
        $courier->goOnline();
        $courier->courierProfile()->update([
            'last_location_at' => now()->subSeconds(121),
        ]);

        (new MarkInactiveCouriers())->handle();

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === 'courier_forced_offline_stale_location'
                && ($context['reason'] ?? null) === 'stale_location_ttl_expired'
                && isset($context['courier_id']);
        })->once();
    }

    public function test_location_tracker_diagnostic_log_identifies_heartbeat_receipt(): void
    {
        Log::spy();
        config()->set('courier_runtime.heartbeat.diagnostic_logging', true);

        $courier = $this->createCourier();
        $courier->goOnline();

        $this->actingAs($courier, 'web');

        Livewire::test(LocationTracker::class)
            ->call('updateLocation', 48.4647, 35.0462, 25);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'courier_location_heartbeat_received'
                && isset($context['courier_id'])
                && array_key_exists('last_location_at', $context);
        })->once();
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
