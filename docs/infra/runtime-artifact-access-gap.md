# Runtime artifact access gap for Codex/debug containers

Date: 2026-05-11

## Problem
Incident/debug containers cannot fetch GitHub Actions runtime artifact `runtime-vendor-${sha}` even when CI uploads it successfully.

## Evidence from this container
- `gh` CLI is not installed (`gh: command not found`).
- No GitHub remote is configured in `.git/config`, so repository owner/name cannot be derived automatically for API calls.
- No GitHub token/credentials are present in environment (`env | rg -i 'github|token|gh_'` only shows `GH_PAGER=cat`).
- Local archive is absent (`vendor.tar.gz` not present in `/workspace/poof-backend`).
- Runtime preflight fails due missing vendor:
  - `runtime_ready=false`
  - `missing_vendor=true`
  - `artisan_bootstrap=false`

## What CI currently does (verified in repo workflow)
`.github/workflows/tests.yml` in job `php-tests`:
1. runs `composer install --no-interaction --prefer-dist --no-progress`;
2. builds `vendor.tar.gz` (`tar -czf vendor.tar.gz vendor`);
3. uploads artifact `runtime-vendor-${{ github.sha }}` with `retention-days: 14`.

So upload exists in workflow definition, but retrieval path is missing in this debug environment.

## Required access path (choose one)
1. Provide direct artifact URL (signed or authenticated) in incident handoff.
2. Mount `vendor.tar.gz` into workspace before Codex session starts.
3. Install `gh` in container and inject read-only token with `actions:read` permission.
4. Provide an internal API/proxy endpoint that returns `runtime-vendor-${sha}` by commit SHA.

## Minimum acceptance for future incidents
- Given commit SHA, container can execute:
  1. `curl/gh` download of `runtime-vendor-${sha}`;
  2. `bash scripts/restore-runtime-from-artifact.sh vendor.tar.gz`;
  3. `bash scripts/check-backend-runtime.sh` with `runtime_ready=true`.
