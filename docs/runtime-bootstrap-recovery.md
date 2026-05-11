# Runtime bootstrap recovery (Laravel backend)

## Purpose
This runbook defines **supported recovery paths** for backend runtime when Laravel bootstrap is blocked (for example, `vendor/autoload.php` missing) and direct `composer install` is not possible due to proxy/network restrictions.

Use this document to avoid repeated failed Composer attempts in blocked environments.

## Symptoms of blocked runtime bootstrap
Typical failure pattern:
- `php artisan --version` fails with `vendor/autoload.php` missing.
- `composer` commands fail with proxy tunnel errors (for example, `CONNECT tunnel failed, response 403`).
- Diagnostic command cannot start:
  - `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`

---

## Supported recovery paths
Choose **one** path below. Do not mix partial artifacts from different build states.

### A) Restore from CI artifact containing `vendor/`
Recommended when CI already produced a build artifact for this exact revision.

1. Identify the artifact built from the same commit SHA as current workspace.
2. Download/extract artifact into project root.
3. Verify `vendor/autoload.php` exists and is readable.
4. Run runtime checklist (see section below).

Notes:
- `vendor/` must match `composer.lock` of current commit.
- If commit mismatch is detected, discard artifact and use another supported path.

### B) Run in prebuilt Docker image with dependencies installed
Recommended for deterministic incident diagnostics.

1. Use approved prebuilt image tag that already includes Composer dependencies.
2. Mount project or run bundled app snapshot according to ops policy.
3. Confirm container PHP/Composer versions meet project requirements.
4. Run runtime checklist inside container.

Notes:
- Prefer immutable image digest over mutable tags for reproducibility.
- Keep container runtime timezone and env aligned with target diagnostic context.

### C) Use approved Composer mirror / proxy allowlist
Use when online dependency resolution is required and security policy allows controlled egress.

1. Configure Composer to use approved mirror (e.g., Private Packagist / Satis) **or** update corporate proxy allowlist.
2. Ensure outbound access works for required hosts (`repo.packagist.org`, `api.github.com`, `getcomposer.org`) or approved mirror equivalents.
3. Run `composer install --no-interaction --prefer-dist --no-progress`.
4. Run runtime checklist.

Notes:
- Avoid ad-hoc public egress exceptions; use approved policy path.
- Persist mirror/proxy config in deployment automation where possible.

### D) Use local `vendor` archive provided manually
Use when an operator provides a trusted archive (e.g., `vendor.tar.gz`) built from matching commit.

1. Validate provenance of archive (source build, commit SHA, timestamp).
2. Extract archive into repo root so `vendor/autoload.php` is present.
3. Confirm file ownership/permissions are correct for runtime user.
4. Run runtime checklist.

Notes:
- Reject archive if commit/lockfile does not match.
- Treat archive as build artifact and store checksum when possible.

---

## Production-safe runtime checklist
Run these checks in order:

1. `php -v`
2. `composer --version`
3. `test -f vendor/autoload.php`
4. `php artisan --version`
5. `php artisan config:show app.timezone` (or equivalent timezone check)
6. `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`

If step 3 or 4 fails, runtime is not ready; return to one of the supported recovery paths.

## Quick automated check
Use helper script:

```bash
bash scripts/check-backend-runtime.sh
```

Machine-readable output keys:
- `runtime_ready=true|false`
- `missing_vendor=true|false`
- `artisan_bootstrap=true|false`
- `composer_network_blocked=true|false`

`composer_network_blocked` is an **optional diagnostic signal** and does not by itself define runtime readiness.
