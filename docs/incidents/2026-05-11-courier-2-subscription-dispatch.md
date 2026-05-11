# Incident report: 2026-05-11 / courier_id=2 / subscription dispatch

## Scope
- Target command: `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`
- Environment date requested: `2026-05-11`
- Courier: `2`
- Commit SHA for runtime artifact: `2df0cf66dfd31be3a5d11b7e6ab22fae4753c563`
- Expected artifact name: `runtime-vendor-2df0cf66dfd31be3a5d11b7e6ab22fae4753c563`

## What was executed
1. Verified availability of artifact download tooling and runtime archive sources.
2. Checked runtime bootstrap status:
   - `bash scripts/check-backend-runtime.sh`
3. Attempted diagnose execution (fails before Laravel boot):
   - `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`

## Runtime recovery status (artifact-first)
Artifact recovery remains blocked in this container:
- `gh` CLI: not installed.
- GitHub artifact token/credentials: not present in environment.
- Local `vendor.tar.gz` archive: not found in `/workspace`.
- `docker` runtime path was not used (not part of this artifact-first task).

Because `vendor/autoload.php` is missing, `scripts/restore-runtime-from-artifact.sh` cannot be executed without first obtaining the archive file path.

## Verification result
Output of `bash scripts/check-backend-runtime.sh`:
- `runtime_ready=false`
- `missing_vendor=true`
- `artisan_bootstrap=false`
- `composer_network_blocked=true`

Expected acceptance (`runtime_ready=true`, `artisan_bootstrap=true`) is **not met**.

## Diagnose output
Final output snapshot was updated in:
- `docs/incidents/2026-05-11-courier-2-subscription-dispatch-output.json`

Diagnose command was blocked by bootstrap error:
- `Failed opening required '/workspace/poof-backend/vendor/autoload.php'`

## Confirmed root cause
- **Confirmed operational root cause for this run:** `другое` → `runtime_artifact_unavailable`.
- **Business root cause candidates** (`dispatch_deferred_future_window`, `no_alive_pending_offer`, `no_offer_for_courier`, `queue issue`, `timezone issue`, `not_paid`) are **not yet evaluable on real data** until artifact restore succeeds.

## Why courier 2 saw no orders
Not confirmable from production-like runtime in this container because Laravel did not bootstrap. No authoritative business diagnosis can be produced yet.

## Exact gating field/filter
Not confirmable yet. The blocking gate is infrastructure-level runtime readiness (`vendor/autoload.php` missing), not a dispatch business filter.

## Stuck dispatch / repair action
No dry-run redispatch command prepared yet because dispatch diagnostics were not executed. Mass redispatch remains explicitly **not performed**.

## Prevention plan
1. Persist a retrievable artifact URL (or signed link) in the incident task handoff, not only artifact name.
2. Ensure CI injects a read-only artifact token (or install `gh` with scoped credentials) in incident-analysis containers.
3. Add preflight check that fails fast when runtime artifact retrieval path is absent.
4. Keep `runtime-vendor-${sha}` retention aligned with incident SLA window.

## Incident status
- **Incident is not closed.**
- Follow-up required: provide actual `vendor.tar.gz` for `runtime-vendor-2df0cf66dfd31be3a5d11b7e6ab22fae4753c563`, then rerun:
  1. `bash scripts/restore-runtime-from-artifact.sh /path/to/vendor.tar.gz`
  2. `bash scripts/check-backend-runtime.sh`
  3. `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`
