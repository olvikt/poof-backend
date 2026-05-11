# Runtime artifacts for backend recovery

## Purpose
This document defines the official artifact-based runtime recovery flow for `poof-backend` when `composer install` cannot run in incident/debug environments.

## Artifact produced by CI
- Workflow: `.github/workflows/tests.yml`
- Job: `php-tests`
- Artifact name format: `runtime-vendor-<commit-sha>`
- File inside artifact: `vendor.tar.gz`
- Storage: GitHub Actions build artifacts for the repository run where the job passed.
- Retention: **14 days**.

## How to get `vendor` artifact
1. Open GitHub Actions run that matches the target commit SHA.
2. Download artifact named `runtime-vendor-<that-sha>`.
3. Place downloaded `vendor.tar.gz` in any accessible local path.

## Restore runtime from artifact
Use helper script:

```bash
bash scripts/restore-runtime-from-artifact.sh /path/to/vendor.tar.gz
```

The script will:
1. Extract `vendor/` into repository root.
2. Validate `vendor/autoload.php`.
3. Run `php artisan --version`.
4. Print machine-readable key/value output.

## Integrity check
Before restore, compute checksum:

```bash
sha256sum /path/to/vendor.tar.gz
```

Recommended:
- Save checksum in incident notes.
- Verify commit SHA from artifact name matches local checkout SHA.
- Do not reuse artifact across different `composer.lock` revisions.

## Incident-debugging usage
After restoration, run:
1. `bash scripts/check-backend-runtime.sh`
2. `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`
3. Save JSON output to `docs/incidents/<incident-id>-output.json` (or incident evidence folder).

## Fallback if artifact is missing
If no matching `runtime-vendor-<sha>` artifact exists (expired/not published), use prebuilt Docker runtime image with dependencies already installed.

Requirements for fallback:
- Use approved image by digest (immutable reference).
- Confirm image PHP runtime matches project requirements.
- Run the same runtime checks and diagnostics inside container.
