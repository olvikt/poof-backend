# Runtime artifact access gap for Codex/debug containers

Date: 2026-05-11

## Final working solution
Chosen mechanism: **C. mounted/shared artifact directory**.

Implemented delivery path:
- new helper `scripts/fetch-runtime-artifact.sh` fetches artifact by commit SHA from shared mount;
- default source directory is `/mnt/runtime-artifacts` (overridable via `RUNTIME_ARTIFACTS_DIR`);
- expected naming is `runtime-vendor-<sha>.tar.gz` (overridable via `RUNTIME_ARTIFACT_PREFIX`);
- if sidecar checksum exists (`.sha256`), script verifies SHA-256 before accepting artifact.

This unblocks debug containers without requiring `gh` installation or GitHub API credentials inside the incident environment.

## Delivery contract
At incident handoff, infra places files into mounted directory:

1. `/mnt/runtime-artifacts/runtime-vendor-<commit_sha>.tar.gz`
2. `/mnt/runtime-artifacts/runtime-vendor-<commit_sha>.tar.gz.sha256` (optional but recommended)

Then container runs:

```bash
scripts/fetch-runtime-artifact.sh <commit_sha> vendor.tar.gz
bash scripts/restore-runtime-from-artifact.sh vendor.tar.gz
bash scripts/check-backend-runtime.sh
php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2
```

## Validation checklist (inside debug container)
- Artifact delivery:
  - `result=ok`
  - `artifact_delivered=true`
- Runtime artifact:
  - `vendor.tar.gz` exists in workspace.
- Integrity:
  - `artifact_sha256=...` printed by fetch script;
  - when `.sha256` exists, `expected_sha256` must match.
- Runtime restore:
  - `result=ok`
  - `runtime_restored=true`
- Runtime readiness:
  - `runtime_ready=true`
  - `missing_vendor=false`
  - `artisan_bootstrap=true`

## Notes from this Codex run
- Delivery mechanism is implemented and wired (`scripts/fetch-runtime-artifact.sh`).
- Full end-to-end execution requires infra to mount real `runtime-vendor-<sha>.tar.gz` into `/mnt/runtime-artifacts` for the incident SHA.
- Once mounted, commands above move incident from infra-blocked to business root-cause diagnosis.
