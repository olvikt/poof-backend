# Incident report: 2026-05-11 / courier_id=2 / subscription dispatch

## Scope
- Target command: `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`
- Commit SHA for runtime artifact: `2df0cf66dfd31be3a5d11b7e6ab22fae4753c563`
- Expected artifact name: `runtime-vendor-2df0cf66dfd31be3a5d11b7e6ab22fae4753c563`

## Runtime bootstrap blocker

### 1) Laravel bootstrap check
Command:
```bash
php artisan --version
```
Result:
- failed, exit code `255`
- error: `Failed opening required '/workspace/poof-backend/vendor/autoload.php'`

### 2) Composer recovery attempt
Command:
```bash
composer install --no-interaction --prefer-dist --no-progress
```
Result:
- failed repeatedly on dist downloads
- representative error:
  - `curl error 56 ... https://api.github.com/repos/...: CONNECT tunnel failed, response 403`
  - also seen: `Proxy CONNECT aborted due to timeout`

Conclusion:
- proxy/network policy blocks CONNECT to `api.github.com` from this environment.
- runtime cannot be restored via direct composer download path.

### 3) Artifact recovery attempt (project-supported path)
Command:
```bash
bash scripts/fetch-runtime-artifact.sh 2df0cf66dfd31be3a5d11b7e6ab22fae4753c563 vendor.tar.gz
```
Result:
- `reason=artifact_not_found_in_shared_dir`
- expected shared dir `/mnt/runtime-artifacts` is not mounted (`No such file or directory`).

## Diagnose status
`php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2` was **not executed** because runtime bootstrap is blocked.

## Real root cause classification
This incident run is currently blocked by **infrastructure runtime bootstrap gap**, not by a business-domain `other` reason:
- missing `vendor/autoload.php`
- proxy-denied composer downloads from `api.github.com`
- unavailable shared runtime artifact mount.

## Required operational action
Open infra ticket to provide one approved bootstrap path:
1. Mount shared CI artifact directory containing `runtime-vendor-<sha>.tar.gz`, or
2. Provide prebuilt Docker runtime image (immutable digest) with `vendor/` preinstalled, or
3. Allow/proxy-route composer traffic to `api.github.com` (optionally with `COMPOSER_AUTH`/token if policy requires auth).

## Closure criteria
Incident must remain **open** until command is executed on a runtime with real dependencies:
```bash
php artisan --version
php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2
```
and outputs are captured in this report + JSON evidence.
