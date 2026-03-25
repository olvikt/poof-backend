# Frontend npm audit high findings (2026-03-25)

## Merged status (PR #337)
PR #337 was merged as a **minimal frontend security maintenance step** with the following outcomes:

- `axios` was upgraded and included in the merged change.
- `package.json` and `package-lock.json` are synchronized.
- frontend CI checks were restored to green (`frontend-build` and `pwa-regression-evidence`).

## Scope intentionally kept small
This merge should be treated as a partial fix and not a full closure of all frontend audit findings.

- The `axios` high finding was addressed in this merged PR.
- The `rollup` advisory is **not claimed as fully closed** in this PR.

## Final rollup verification attempt (2026-03-25)
A follow-up verification was executed in this repository on **March 25, 2026** with a network-enabled runner.

### Commands executed
1. `npm ci`
2. `npm run build`
3. `npm audit --package-lock-only --audit-level=high`

### Results
- `npm ci` failed with `403 Forbidden` while downloading npm tarballs from `https://registry.npmjs.org/`.
- `npm audit` failed with `403 Forbidden` on the npm advisory endpoint (`/-/npm/v1/security/advisories/bulk`).
- Because package installation was blocked by registry policy, `npm run build` could not be rerun in this environment as part of this verification pass.

### Dependency-tree check for rollup
- Current lockfile resolution still includes `rollup@4.55.1` through `vite`.
- The current GitHub-reviewed high advisory for Rollup 4 (CVE-2026-27606 / GHSA-mw96-cpmx-2vgc) affects `>=4.0.0, <4.59.0` and is patched in `4.59.0`.
- Therefore, based on the resolved tree currently committed, the rollup high advisory still applies.

## Final status: accepted (explicitly time-boxed)
`rollup` is currently tracked as **accepted risk (temporary)**, not fixed.

### Rationale
- A safe lockfile regeneration and audit closure requires npm registry and advisory endpoint access that is currently denied (`403`).
- The repository remains on `rollup@4.55.1`, which is below the patched `4.59.0` threshold.

### Required closure step
When registry access is restored, run this exact closure sequence and update this note:

1. Regenerate lockfile with patched Rollup in the resolved tree (target `rollup >= 4.59.0` via `vite` resolution).
2. `npm ci`
3. `npm run build`
4. `npm audit`
5. Mark status as **fixed** once all checks pass and no high Rollup finding remains.

## Traceability note
This keeps the current merged change small, reviewable, and safe to ship, while preserving explicit traceability for the remaining advisory follow-up.
