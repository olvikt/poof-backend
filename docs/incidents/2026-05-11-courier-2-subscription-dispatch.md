# Incident report: 2026-05-11 / courier_id=2 / subscription dispatch

## Scope
- Target command: `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`
- Runtime artifact SHA: `2df0cf66dfd31be3a5d11b7e6ab22fae4753c563`
- Expected artifact: `runtime-vendor-2df0cf66dfd31be3a5d11b7e6ab22fae4753c563.tar.gz`
- Artifact mount contract: `/mnt/runtime-artifacts`

## Diagnose flow execution (2026-05-11)
1. Prepared mount point:
   - `mkdir -p /mnt/runtime-artifacts`
2. Tried artifact fetch:
   - `scripts/fetch-runtime-artifact.sh 2df0cf66dfd31be3a5d11b7e6ab22fae4753c563 vendor.tar.gz`
   - Result:
     - `result=error`
     - `reason=artifact_not_found_in_shared_dir`
     - `expected_artifact=runtime-vendor-2df0cf66dfd31be3a5d11b7e6ab22fae4753c563.tar.gz`
3. Follow-up checks mandated by flow:
   - `ls -lh vendor.tar.gz` → file not found
   - `sha256sum vendor.tar.gz` → file not found
4. Runtime restore attempt:
   - `bash scripts/restore-runtime-from-artifact.sh vendor.tar.gz`
   - Result: `reason=artifact_not_found`
5. Runtime health check:
   - `bash scripts/check-backend-runtime.sh`
   - Output:
     - `runtime_ready=false`
     - `missing_vendor=true`
     - `artisan_bootstrap=false`
6. Diagnose command run attempt:
   - `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`
   - Failed before command logic: `vendor/autoload.php` missing

## Final business root cause
`другое` → **runtime_artifact_unavailable_in_mount_dir**.

Interpretation:
- This is not yet a business-layer root cause among (`dispatch_deferred_future_window`, `no_alive_pending_offer`, `no_offer_for_courier`, `queue issue`, `timezone issue`, `not_paid`).
- The incident remains blocked on infrastructure precondition: real CI runtime artifact is not mounted/provided in `/mnt/runtime-artifacts`.

## Backend bug / fix PR assessment
- No backend logic bug identified at this stage because diagnose command could not execute past bootstrap.
- Therefore, no separate backend fix PR or regression test is created yet.

## Stuck dispatch / repair assessment
- No stuck-dispatch evidence could be produced without successful diagnose output.
- Dry-run repair command is deferred until runtime artifact is available and diagnose command returns structured domain result.

## Current status
**Incident is not closed**. It can be closed only after:
1. Real CI artifact appears in `/mnt/runtime-artifacts/runtime-vendor-2df0cf66dfd31be3a5d11b7e6ab22fae4753c563.tar.gz`.
2. Runtime restore succeeds.
3. Diagnose command completes and yields factual dispatch root cause from business domain.
