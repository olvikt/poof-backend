# Incident report: 2026-05-11 / courier_id=2 / subscription dispatch

## Scope
- Target command: `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`
- Environment date requested: `2026-05-11`
- Courier: `2`

## Execution result
The diagnose command was **not executable** in this container because Laravel bootstrap failed before app start:
- missing `vendor/autoload.php`;
- `composer install` attempted dependency restoration but failed on network/proxy access to GitHub (`CONNECT tunnel failed, response 403`).

Saved raw execution status:
- `docs/incidents/2026-05-11-courier-2-subscription-dispatch-output.json`

## Final incident verdict
- **Root cause code:** `other`
- **Final verdict:** In this runtime, incident root cause for courier visibility on 2026-05-11 cannot be derived because diagnostic instrumentation could not be executed against real data.

## Required counters (active/today, dispatchable, deferred, stuck_without_offers)
Unavailable from this run due to bootstrap failure.

## Why courier_id=2 did not see orders
Not determinable from this run: no domain diagnostic JSON (`orders`, `summary`, `queue`, `timezone`) was produced by artisan command.

## Fix vs recovery
- Application-code fix for dispatch logic: **not evidenced** by this run.
- Operational recovery required first: restore dependency installation/network path (or run on prebuilt environment with valid `vendor/`) and rerun diagnose command on real 2026-05-11 dataset.

## Exact blocking filters/fields
Domain-level filters (`status`, `payment_status`, dispatch window, offers, queue lag, timezone) were not evaluated because the command did not reach application runtime. Blocking layer was infrastructure/bootstrap:
- missing file: `vendor/autoload.php`;
- dependency fetch blocked by outbound/proxy restrictions.
