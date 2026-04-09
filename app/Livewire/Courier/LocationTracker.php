<?php

namespace App\Livewire\Courier;

use App\Models\Courier;
use App\Models\User;
use App\Services\Courier\LocationIngestService;
use App\Services\Courier\CourierPresenceService;
use App\Services\Dispatch\DispatchTriggerPolicy;
use App\Services\Dispatch\DispatchTriggerService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class LocationTracker extends Component
{
    /**
     * JS → Livewire listener
     */
    protected $listeners = [
        'courier-location' => 'updateLocation',
    ];

    public function booted(): void
    {
        $this->dispatch('courier:tracker-ready');
    }

    /**
     * 📍 Получение координат от фронта
     * ⚠ Без type-hint'ов (требование Livewire listeners)
     */
    public function mount(): void
    {
        $user = $this->presenceService()->resolveAuthenticatedCourier();

        if (! $user instanceof User || ! $user->courierProfile) {
            return;
        }

        $courierProfile = $user->courierProfile;
        $runtime = $this->presenceService()->snapshot($user) ?? [];

        $this->dispatch(
            'courier:runtime-sync',
            version: 1,
            source: 'location_tracker_mount',
            reason: 'canonical_snapshot_sync',
            changed: false,
            online: (bool) ($runtime['online'] ?? false),
            status: (string) ($runtime['status'] ?? $courierProfile->status),
            snapshot: $runtime,
        );

        if (config('courier_runtime.incident_logging.enabled', false)) {
            Log::info('runtime_sync_event_emitted', [
                'flow' => 'courier_location',
                'courier_id' => $user->id,
                'event' => 'courier:runtime-sync',
            ]);

            Log::info('runtime_sync_event_payload', [
                'flow' => 'courier_location',
                'courier_id' => $user->id,
                'payload' => [
                    'version' => 1,
                    'source' => 'location_tracker_mount',
                    'reason' => 'canonical_snapshot_sync',
                    'changed' => false,
                    'online' => (bool) ($runtime['online'] ?? false),
                    'status' => (string) ($runtime['status'] ?? $courierProfile->status),
                    'snapshot' => $runtime,
                ],
            ]);
        }

        if (config('courier_runtime.incident_logging.enabled', false)) {
            Log::info('courier_runtime_snapshot_synced', [
                'flow' => 'courier_location',
                'courier_id' => $user->id,
                'snapshot' => $runtime,
                'last_location_at' => $courierProfile->last_location_at?->toIso8601String(),
            ]);
        }

        if (
            in_array((string) ($runtime['status'] ?? Courier::STATUS_OFFLINE), Courier::ACTIVE_MAP_STATUSES, true) &&
            $user->last_lat &&
            $user->last_lng
        ) {
            $this->dispatch('map:courier-update', [
                'courier' => [
                    'lat' => $user->last_lat,
                    'lng' => $user->last_lng,
                ],
            ]);
        }
    }

    public function updateLocation($lat, $lng, $accuracy = null): void
    {
        $user = $this->presenceService()->resolveAuthenticatedCourier();

        // 🔒 Только авторизованный курьер
        if (! $user instanceof User || ! $user->isCourier()) {
            return;
        }

        if (! $user->courierProfile) {
            return;
        }

        // Приведение типов
        $lat = (float) $lat;
        $lng = (float) $lng;
        $accuracy = $accuracy !== null ? (float) $accuracy : null;
        $maxAccuracyMeters = (float) config('courier_runtime.heartbeat.max_accuracy_meters', 120);

        // Resolve canonical runtime once per request.
        $runtime = $this->presenceService()->snapshot($user) ?? [];
        $previousLat = $user->last_lat !== null ? (float) $user->last_lat : null;
        $previousLng = $user->last_lng !== null ? (float) $user->last_lng : null;
        $ingestResult = $this->locationIngestService()->ingest($user, $lat, $lng, $accuracy, $runtime);

        if (! ($ingestResult['accepted'] ?? false)) {
            $reason = (string) ($ingestResult['reason'] ?? 'unknown');

            if ($reason === 'invalid_coordinates') {
                Log::warning('courier_location_rejected_invalid_coordinates', [
                    'flow' => 'courier_location',
                    'courier_id' => $user->id,
                    'lat' => $lat,
                    'lng' => $lng,
                ]);
            }

            if ($reason === 'low_accuracy') {
                Log::debug('courier_location_rejected_low_accuracy', [
                    'flow' => 'courier_location',
                    'courier_id' => $user->id,
                    'accuracy' => $accuracy,
                    'max_accuracy_meters' => $maxAccuracyMeters,
                ]);
            }

            if ($reason === 'runtime_not_active_map_status') {
                Log::debug('courier_location_rejected_runtime_not_active', [
                    'flow' => 'courier_location',
                    'courier_id' => $user->id,
                    'runtime_status' => (string) ($runtime['status'] ?? Courier::STATUS_OFFLINE),
                ]);
            }

            Log::warning('courier_location_rejected', [
                'flow' => 'courier_location',
                'courier_id' => $user->id,
                'reason' => $reason,
            ]);
            return;
        }

        $distanceMoved = null;

        if ($previousLat !== null && $previousLng !== null) {
            $distanceMoved = $this->distanceMeters(
                $previousLat,
                $previousLng,
                $lat,
                $lng
            );
        }

        $movementThresholdMeters = (float) config('dispatch.trigger.location_movement_threshold_meters', 50);
        $hasMovedEnough = $distanceMoved === null || $distanceMoved > $movementThresholdMeters;

        $dispatchedCount = app(DispatchTriggerService::class)->triggerQueueBatch(
            DispatchTriggerPolicy::SOURCE_LOCATION_UPDATE,
            (int) config('dispatch.radius_km', 20),
            [
                'courier_id' => $user->id,
                'online' => (bool) ($runtime['online'] ?? false),
                'distance_moved' => $distanceMoved,
                'has_moved_enough' => $hasMovedEnough,
                'movement_threshold_meters' => $movementThresholdMeters,
            ],
        );

        if (config('courier_runtime.heartbeat.diagnostic_logging', false)) {
            Log::info('courier_location_heartbeat_received', [
                'flow' => 'courier_location',
                'courier_id' => $user->id,
                'accuracy' => $accuracy,
                'max_accuracy_meters' => $maxAccuracyMeters,
                'courier_status' => (string) optional($user->courierProfile)->status,
                'last_location_at' => optional($user->courierProfile?->fresh())->last_location_at?->toIso8601String(),
                'online' => (bool) ($runtime['online'] ?? false),
            ]);
        }

        if ($dispatchedCount > 0) {
            $user->update(['last_dispatch_at' => now()]);
        }

        // -------------------------------------------------
        // Обновляем карту (JS)
        // -------------------------------------------------

        $this->dispatch('map:courier-update', [
            'courier' => [
                'lat' => $lat,
                'lng' => $lng,
            ],
        ]);
    }

    protected function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;

        $latDiff = deg2rad($lat2 - $lat1);
        $lngDiff = deg2rad($lng2 - $lng1);

        $a = sin($latDiff / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDiff / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }

    private function presenceService(): CourierPresenceService
    {
        return app(CourierPresenceService::class);
    }

    private function locationIngestService(): LocationIngestService
    {
        return app(LocationIngestService::class);
    }

    /**
     * Headless component
     */
    public function render()
    {
        $user = $this->presenceService()->resolveAuthenticatedCourier();
        $runtime = $this->presenceService()->snapshot($user);

        return view('livewire.courier.location-tracker', [
            'runtimeSnapshot' => $runtime,
        ]);
    }
}
