# Frontend npm audit high findings (2026-03-25)

## Findings identified
From the local npm debug audit report, the two high findings are:

1. `axios` — GHSA-43fc-jf86-j433, vulnerable range `>=1.0.0 <=1.13.4`.
2. `rollup` — GHSA-mw96-cpmx-2vgc, vulnerable range `>=4.0.0 <4.59.0`.

## Dependency paths
- `axios` is a direct production dependency (`dependencies.axios`) used by frontend runtime code.
- `rollup` is pulled transitively by `vite` (`vite -> rollup`) and is used for development/build tooling.

## Production vs development impact
- `axios`: production/runtime dependency (affects shipped frontend runtime path).
- `rollup`: development/build-time dependency (used while building bundles; not shipped as runtime library).

## Mitigation applied in this change
- Bumped `axios` range to `^1.13.5`.
- Added npm override `rollup: ^4.59.0`.

## Verification status in this environment
- Could not refresh lockfile due registry proxy access (`npm install --package-lock-only` returned 403).
- Frontend build command currently fails on missing Livewire ESM path in this environment (`../../vendor/livewire/livewire/dist/livewire.esm`), which is separate from these dependency range changes.

## Final status
- `axios`: **mitigated by declared upgrade**, pending lockfile regeneration in a network-enabled environment.
- `rollup`: **mitigated by override**, pending lockfile regeneration in a network-enabled environment.
- Explicit acceptance: residual scanner findings may remain until lockfile is regenerated and `npm audit` can be rerun successfully.
