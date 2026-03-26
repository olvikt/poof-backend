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

## Re-validation and mitigation update (2026-03-26)

### Current lockfile state
- `vite` resolved to `7.3.1`.
- `rollup` resolved to `4.60.0`.
- `axios` resolved to `1.13.5`.

### Advisory focus
The previously tracked high frontend advisory was **Vite dev-server authorization bypass** (`CVE-2025-31486`, high severity), affecting vulnerable Vite branches below patched releases.

### Exposure assessment in this repository
- Dependency type: **direct `devDependency`** (`vite`).
- Reachability: **build/dev tooling only**; not shipped to browser runtime bundle.
- Practical exploitability requires a network-exposed Vite dev server.

### Additional containment applied
- `vite.config.js` now pins `server.host` to `127.0.0.1` by default.
- This keeps the Vite dev server local-only unless an operator explicitly overrides host settings.

### Audit command note
`npm audit` could not be queried in this environment on 2026-03-26 due registry endpoint denial (`403 Forbidden` on `/-/npm/v1/security/advisories/bulk`).

## Final status
- No production runtime frontend package is known vulnerable from the current lockfile set.
- The historical high advisory path remains addressed, and local-only Vite hosting is now explicitly enforced as defense-in-depth.

## Traceability note
This preserves the same constrained scope used for frontend dependency maintenance while updating the audit record with a current exposure analysis and explicit mitigation.
