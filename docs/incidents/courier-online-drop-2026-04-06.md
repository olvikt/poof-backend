# Incident: courier online drop after release-20260406-courier-runtime-hardening-491

## Symptom
Courier switched to **online** but got auto-dropped to **offline** during a healthy session (typically after ~30/50/60 seconds).

## Impact
P0 / release-blocker: courier availability became unstable, which breaks core dispatch operations.

## Root cause
Frontend heartbeat pipeline accepted location accuracy up to **120m**, while backend `LocationTracker` rejected heartbeat updates above **100m**.

As a result, legitimate heartbeat updates (e.g. 101–120m, typical for mobile/background conditions) were silently dropped by backend validation. `last_location_at` stopped refreshing and stale sweeper marked courier offline on next scheduler tick.

Observed 30/50/60 second pattern is explained by minute scheduler alignment, not by deterministic session timeout.

## Fix
1. Introduced unified courier runtime config (`config/courier_runtime.php`) with explicit heartbeat/staleness contract.
2. Aligned backend heartbeat acceptance threshold to canonical config default **120m** (same as frontend guard).
3. Switched stale/freshness numeric literals to config-backed thresholds for map/dispatch/offline sweeper paths.
4. Added controlled diagnostics:
   - heartbeat receipt logs (opt-in),
   - runtime snapshot sync logs (opt-in),
   - stale sweep forced-offline reason log.

## Prevention / follow-up
- Keep heartbeat acceptance thresholds in a single config contract and avoid duplicate literals in JS/PHP paths.
- Keep stale/offline transitions reasoned and log-attributed.
- Add a JS/runtime integration check that asserts frontend and backend heartbeat threshold parity.
