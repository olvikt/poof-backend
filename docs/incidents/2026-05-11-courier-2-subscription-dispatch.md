# Incident report: 2026-05-11 / courier_id=2 / subscription dispatch

## Scope
- Target command: `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`
- Commit SHA for runtime artifact: `2df0cf66dfd31be3a5d11b7e6ab22fae4753c563`
- Expected artifact name: `runtime-vendor-2df0cf66dfd31be3a5d11b7e6ab22fae4753c563`

## Task status
Artifact delivery to this Codex/debug environment is still blocked by infrastructure access gap.

## What was verified
1. Workflow definition confirms artifact creation in CI after composer install:
   - `composer install --no-interaction --prefer-dist --no-progress`
   - `tar -czf vendor.tar.gz vendor`
   - upload `runtime-vendor-${{ github.sha }}` (`retention-days: 14`)
2. Local environment checks:
   - `gh` CLI not installed.
   - `.git/config` has no `origin` remote, so owner/repo cannot be derived for API calls.
   - GitHub credentials/token not present in environment.
   - `vendor.tar.gz` absent in workspace.
3. Runtime remains not ready:
   - `runtime_ready=false`
   - `missing_vendor=true`
   - `artisan_bootstrap=false`

## Consequence
Because artifact cannot be retrieved in-container, the following steps are blocked:
- `ls -lh vendor.tar.gz`
- `sha256sum vendor.tar.gz`
- `bash scripts/restore-runtime-from-artifact.sh vendor.tar.gz`
- successful `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`

## Follow-up
See dedicated infrastructure gap doc with required access path options:
- `docs/infra/runtime-artifact-access-gap.md`

Incident remains open until artifact access path is provided and diagnose can be re-run on restored runtime.

## 2026-05-11 root-cause update (dispatch date mismatch)
- Confirmed code-level cause in `GenerateSubscriptionExecutionOrdersCommand::resolveGenerationSlot()`:
  scheduler compared `next_run_at < now` with second precision.
- When cron/job ran at `14:00:01` for subscription slot `14:00:00` and frequency `every_3_days`, slot was treated as missed and advanced to `2026-05-14 14:00:00`.
- Result: execution orders were generated on `2026-05-11` (order `created_at`), but with `scheduled_date/dispatch_available_at` on `2026-05-14`, therefore courier dispatch remained correctly deferred until 14.05.
- Fix: comparison is now minute-granular (`setSecond(0)`), so second-level drift no longer skips to next frequency slot.
