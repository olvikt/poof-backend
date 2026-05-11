# Incident report: 2026-05-11 / courier_id=2 / subscription dispatch

## Scope
- Target command: `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`
- Environment date requested: `2026-05-11`
- Courier: `2`

## Runtime recovery path for this run (artifact-first only)
Composer-based bootstrap was intentionally skipped as a primary path.

Attempted supported artifact paths from `docs/runtime-bootstrap-recovery.md`:
1. **A) CI vendor artifact** — not executable in this container because no artifact download endpoint/token/tooling was provided.
2. **B) Prebuilt Docker image** — not executable in this container because `docker` CLI/runtime is absent.
3. **D) Local vendor archive** — checked local workspace; no `vendor.tar.gz` (or equivalent) archive is present.

Validation command executed:
- `bash scripts/check-backend-runtime.sh`

Observed runtime status:
- `runtime_ready=false`
- `missing_vendor=true`
- `artisan_bootstrap=false`
- `composer_network_blocked=true`

## Diagnose command status
`php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2` was **not executed**, because Laravel bootstrap still fails (`vendor/autoload.php` missing).

Saved output snapshot:
- `docs/incidents/2026-05-11-courier-2-subscription-dispatch-output.json`

## Current root cause for this run (operational)
- Classification: **другое (operational)**
- Root cause: **approved runtime artifact/image was not available in the current container**, therefore runtime could not be bootstrapped and business dispatch diagnosis could not start.

## Business dispatch root cause
Not determined yet on real data. This report no longer treats `composer install` as the primary remediation path.

## Next required action
1. Provide exactly one approved runtime source:
   - CI `vendor.tar.gz` from matching commit SHA, or
   - prebuilt `poof-backend` image with installed `vendor/`, or
   - trusted local `vendor` archive built from matching lockfile.
2. Re-run `bash scripts/check-backend-runtime.sh` and require:
   - `runtime_ready=true`
   - `missing_vendor=false`
   - `artisan_bootstrap=true`
3. Run diagnose command and persist real output JSON.
4. If `stuck_without_offers > 0`, prepare a separate dry-run repair task/PR; do not execute mass redispatch immediately.
