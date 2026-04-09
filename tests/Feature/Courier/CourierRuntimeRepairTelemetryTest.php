<?php

declare(strict_types=1);

namespace Tests\Feature\Courier;

use App\Models\Courier;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CourierRuntimeRepairTelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_with_no_actual_changes_does_not_emit_write_marker(): void
    {
        Log::fake();

        $user = $this->makeCourierUser(Courier::STATUS_ONLINE);
        $user->update([
            'is_online' => true,
            'is_busy' => false,
            'session_state' => User::SESSION_READY,
        ]);

        $user->repairCourierRuntimeState();

        Log::assertNotLogged('info', fn (string $message): bool => $message === 'courier_runtime_repair_write');
    }

    public function test_snapshot_read_path_with_no_drift_does_not_emit_repair_write_marker(): void
    {
        Log::fake();

        $user = $this->makeCourierUser(Courier::STATUS_ONLINE);
        $user->update([
            'is_online' => true,
            'is_busy' => false,
            'session_state' => User::SESSION_READY,
        ]);

        $runtime = $user->courierRuntimeSnapshot();

        $this->assertTrue((bool) ($runtime['online'] ?? false));
        Log::assertNotLogged('info', fn (string $message): bool => $message === 'courier_runtime_repair_write');
    }

    public function test_repair_with_changed_mirrors_emits_marker(): void
    {
        Log::fake();

        $user = $this->makeCourierUser(Courier::STATUS_ONLINE);
        $user->update([
            'is_online' => false,
            'is_busy' => true,
            'session_state' => User::SESSION_OFFLINE,
        ]);

        $user->repairCourierRuntimeState();

        Log::assertLogged('info', fn (string $message, array $context): bool => $message === 'courier_runtime_repair_write'
            && ($context['user_id'] ?? null) === $user->id
            && isset($context['field_changes']['is_online'])
            && ($context['counter'] ?? null) === 'courier_runtime_repair_writes_total'
            && ($context['counter_labels']['field'] ?? null) === 'is_online'
            && ($context['counter_labels']['repair_type'] ?? null) === 'compatibility_projection');
    }

    public function test_emitted_payload_contains_changed_fields(): void
    {
        Log::fake();

        $user = $this->makeCourierUser(Courier::STATUS_PAUSED);
        $user->repairCourierRuntimeState();

        Log::assertLogged('info', function (string $message, array $context): bool {
            if ($message !== 'courier_runtime_repair_write') {
                return false;
            }

            $changes = $context['field_changes'] ?? [];

            return isset($changes['status']) || isset($changes['is_online']) || isset($changes['session_state']);
        });
    }

    public function test_normal_non_repair_path_does_not_emit_false_markers(): void
    {
        Log::fake();

        $user = $this->makeCourierUser(Courier::STATUS_OFFLINE);

        $user->transitionCourierState(Courier::STATUS_ONLINE);

        Log::assertNotLogged('info', fn (string $message): bool => $message === 'courier_runtime_repair_write');
    }

    private function makeCourierUser(string $status): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_COURIER,
            'is_active' => true,
            'is_online' => false,
            'is_busy' => false,
            'session_state' => User::SESSION_OFFLINE,
        ]);

        Courier::query()->create([
            'user_id' => $user->id,
            'status' => $status,
            'last_location_at' => now(),
        ]);

        return $user;
    }
}
