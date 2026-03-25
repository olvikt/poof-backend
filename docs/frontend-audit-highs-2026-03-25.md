# Frontend npm audit high findings (2026-03-25)

## Merged status (PR #337)
PR #337 was merged as a **minimal frontend security maintenance step** with the following outcomes:

- `axios` was upgraded and included in the merged change.
- `package.json` and `package-lock.json` are synchronized.
- frontend CI checks were restored to green (`frontend-build` and `pwa-regression-evidence`).

## Follow-up completion for Rollup advisory (post-merge, PR #339)
A network-enabled follow-up lockfile refresh was completed to close the remaining Rollup advisory work.

### Verified outcomes
- `npm audit fix` completed successfully.
- Resolved Rollup version is now `4.60.0`.
- `npm audit` reports `0 vulnerabilities`.
- `npm run build` passes.

### Scope confirmation
- Lockfile refresh only.
- No production/runtime PHP changes.

## Final status: fixed
The previous temporary accepted-risk state for the Rollup advisory is now closed as **fixed** after the lockfile refresh and verification sequence above.

## Traceability note
This preserves the same constrained scope used for frontend dependency maintenance while updating the audit record to reflect the verified fixed state.
