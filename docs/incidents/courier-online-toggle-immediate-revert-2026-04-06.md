# Incident: courier online toggle immediate revert (2026-04-06)

## Symptom
After pressing **"На лінії / Не на лінії"**, courier UI briefly switched to online and then quickly returned to offline.

## Impact
Courier could not keep stable online runtime state, which blocked order intake and became a release blocker.

## Root cause
`OnlineToggle` successfully persisted `couriers.status=online`, but stale sweeper (`MarkInactiveCouriers`) treated `couriers.last_location_at = null` as immediately stale and forced courier back to offline on the next sweep cycle.

This produced a fast canonical revert chain:
1. toggle writes online status;
2. sweeper sees null/expired `last_location_at` and force-offlines courier;
3. next Livewire hydrate/snapshot reflects offline again and UI reverts.

## Exact conflicting paths
- Write path: `OnlineToggle -> CourierPresenceService::toggleOnline() -> User::goOnline() -> transitionCourierState(status=online)`.
- Revert path: `MarkInactiveCouriers::handle()` stale check `status=online && (last_location_at is null || too old)` then `goOffline()`.

## Fix
- On canonical transition to `couriers.status=online`, backend now also seeds `couriers.last_location_at=now()` in the same transition write.
- Added controlled incident diagnostics (feature-flagged):
  - `online_toggle_requested`
  - `online_toggle_persisted`
  - `online_toggle_snapshot_after_write`
  - `online_toggle_snapshot_after_hydrate`
  - `runtime_sync_event_emitted`
  - `runtime_sync_event_payload`
  - `forced_repair_or_guard_reason`

## Regression prevention
- Added feature tests covering:
  - durable online persistence after click;
  - no immediate revert on hydrate;
  - stale/runtime-sync events do not overwrite fresh canonical toggle;
  - LocationTracker runtime sync keeps online state and logs payload;
  - explicit blocked reason for business guards.
