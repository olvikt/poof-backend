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

## Remaining follow-up work
To complete the unresolved audit verification:

1. Re-check the `rollup` advisory in a network-enabled environment.
2. Regenerate lockfile / resolved dependency tree there if the advisory still applies.
3. Re-run and confirm final `npm audit` status after that refresh.

## Traceability note
This keeps the current merged change small, reviewable, and safe to ship, while preserving explicit traceability for the remaining advisory follow-up.
