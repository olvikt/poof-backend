<?php

namespace App\Jobs;

use App\Models\Courier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MarkInactiveCouriers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $staleSeconds = max(30, (int) config('courier_runtime.freshness.offline_stale_seconds', 120));

        Courier::query()
            ->with('user')
            ->chunkById(200, function ($couriers) use ($staleSeconds): void {
                foreach ($couriers as $courier) {
                    $user = $courier->user;

                    if (! $user) {
                        continue;
                    }

                    // Self-heal: сначала всегда выравниваем runtime/business состояние.
                    $user->repairCourierRuntimeState();

                    $courier->refresh();

                    $isStaleOnline = $courier->status === Courier::STATUS_ONLINE
                        && (
                            $courier->last_location_at === null
                            || $courier->last_location_at->lte(now()->subSeconds($staleSeconds))
                        );

                    if (! $isStaleOnline || $user->hasActiveCourierOrder()) {
                        if (config('courier_runtime.incident_logging.enabled', false)) {
                            Log::debug('courier_stale_sweep_skipped', [
                                'flow' => 'courier_stale_sweep',
                                'courier_id' => $user->id,
                                'courier_status' => (string) $courier->status,
                                'last_location_at' => $courier->last_location_at?->toIso8601String(),
                                'stale_threshold_seconds' => $staleSeconds,
                                'is_stale_online' => $isStaleOnline,
                                'has_active_order' => $user->hasActiveCourierOrder(),
                            ]);
                        }

                        continue;
                    }

                    Log::warning('courier_forced_offline_stale_location', [
                        'flow' => 'courier_stale_sweep',
                        'courier_id' => $user->id,
                        'courier_status_before' => (string) $courier->status,
                        'last_location_at' => $courier->last_location_at?->toIso8601String(),
                        'stale_threshold_seconds' => $staleSeconds,
                        'reason' => 'stale_location_ttl_expired',
                    ]);

                    $user->goOffline();
                }
            });
    }
}
