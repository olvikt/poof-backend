<?php

declare(strict_types=1);

namespace App\Services\Courier;

use App\Models\Courier;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LocationIngestService
{
    /**
     * @param  array<string,mixed>|null  $runtimeSnapshot
     * @return array{accepted:bool,reason:string,status_sync_reason:string,status_synced:bool,raw_status_was_stale:bool}
     */
    public function ingest(User $courier, float $lat, float $lng, ?float $accuracy, ?array $runtimeSnapshot): array
    {
        $profile = $courier->courierProfile;

        if (! $profile) {
            return $this->rejected('missing_courier_profile');
        }

        if (! $this->validCoords($lat, $lng)) {
            return $this->rejected('invalid_coordinates');
        }

        $maxAccuracyMeters = (float) config('courier_runtime.heartbeat.max_accuracy_meters', 120);
        if ($accuracy !== null && $accuracy > $maxAccuracyMeters) {
            return $this->rejected('low_accuracy');
        }

        $runtimeStatus = (string) (($runtimeSnapshot['status'] ?? null) ?: Courier::STATUS_OFFLINE);
        $hasActiveOrder = (bool) ($runtimeSnapshot['has_active_order'] ?? false);
        $rawStatus = (string) $profile->status;

        if (! in_array($runtimeStatus, Courier::ACTIVE_MAP_STATUSES, true)) {
            return $this->rejected('runtime_not_active_map_status');
        }

        $statusSyncReason = $this->resolveStatusSyncReason($rawStatus, $runtimeStatus, $hasActiveOrder);
        $statusSynced = false;
        $rawStatusWasStale = ! in_array($rawStatus, Courier::ACTIVE_MAP_STATUSES, true)
            && in_array($runtimeStatus, Courier::ACTIVE_MAP_STATUSES, true);

        if ($rawStatus !== $runtimeStatus && $statusSyncReason !== 'no_sync_needed') {
            $profile->update(['status' => $runtimeStatus]);
            $statusSynced = true;
        }

        $now = now();

        $courier->update([
            'last_lat' => $lat,
            'last_lng' => $lng,
            'last_seen_at' => $now,
        ]);

        $profile->update([
            'last_location_at' => $now,
        ]);

        $this->emitStatusSyncTelemetry(
            courierId: (int) $courier->id,
            statusSyncReason: $statusSyncReason,
            statusSynced: $statusSynced,
            rawStatusWasStale: $rawStatusWasStale,
            rawStatus: $rawStatus,
            runtimeStatus: $runtimeStatus,
        );

        return [
            'accepted' => true,
            'reason' => 'ok',
            'status_sync_reason' => $statusSyncReason,
            'status_synced' => $statusSynced,
            'raw_status_was_stale' => $rawStatusWasStale,
        ];
    }

    private function validCoords(float $lat, float $lng): bool
    {
        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }

    private function resolveStatusSyncReason(string $rawStatus, string $runtimeStatus, bool $hasActiveOrder): string
    {
        if ($rawStatus === $runtimeStatus) {
            return 'no_sync_needed';
        }

        if ($hasActiveOrder) {
            return 'active_order_status_enforced';
        }

        if ($rawStatus === Courier::STATUS_OFFLINE && in_array($runtimeStatus, Courier::ACTIVE_MAP_STATUSES, true)) {
            return 'offline_status_stale';
        }

        return 'no_sync_needed';
    }

    /**
     * @return array{accepted:bool,reason:string,status_sync_reason:string,status_synced:bool,raw_status_was_stale:bool}
     */
    private function rejected(string $reason): array
    {
        return [
            'accepted' => false,
            'reason' => $reason,
            'status_sync_reason' => 'no_sync_needed',
            'status_synced' => false,
            'raw_status_was_stale' => false,
        ];
    }

    private function emitStatusSyncTelemetry(
        int $courierId,
        string $statusSyncReason,
        bool $statusSynced,
        bool $rawStatusWasStale,
        string $rawStatus,
        string $runtimeStatus,
    ): void {
        Log::info('location_ingest_status_sync_boundary', [
            'flow' => 'courier_location',
            'courier_id' => $courierId,
            'status_synced' => $statusSynced,
            'raw_status_was_stale' => $rawStatusWasStale,
            'raw_status' => $rawStatus,
            'runtime_status' => $runtimeStatus,
            'counter' => 'location_ingest_status_sync_total',
            'counter_increment' => 1,
            'counter_labels' => [
                'reason' => $statusSyncReason,
            ],
        ]);

        if (! $rawStatusWasStale) {
            return;
        }

        Log::info('location_ingest_heartbeat_accepted_with_stale_raw_status', [
            'flow' => 'courier_location',
            'courier_id' => $courierId,
            'raw_status' => $rawStatus,
            'runtime_status' => $runtimeStatus,
            'counter' => 'location_ingest_accepted_stale_raw_status_total',
            'counter_increment' => 1,
            'counter_labels' => [
                'reason' => $statusSyncReason,
            ],
        ]);
    }
}
