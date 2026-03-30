# Blocking browser/e2e lane (minimal interactive regression guard)

## Scope

This lane is intentionally narrow and blocks PRs on critical interactive failures only.

Covered incident-risk classes:
- runtime bootstrap in a real browser session;
- dead-click / dead-UI regressions (Livewire/XHR/interaction viability);
- client critical create/save interactions;
- courier accept/start/complete lifecycle transitions;
- `wire:navigate` transitions between courier tabs;
- modal/bottom-sheet open → interact → close lifecycle.

## Included scenarios

Playwright spec: `tests/e2e/specs/minimal-blocking-interactions.spec.js`

1. **F + A(min):** runtime bootstrap + client order-create screen + basic form interaction milestone.
2. **B(min):** authenticated client profile page remains interactive and logout flow works.
3. **D + C(min):** courier can go online and reaches actionable/searching runtime state (`accept` when available).

## Selector policy (blocking lane stability)

- Prefer user-facing selectors (`getByRole`, `getByLabel`, `getByText`) where contract is stable.
- For dynamic sheets/toggles/live widgets, use explicit `data-e2e` hooks.
- Current Playwright config uses `data-e2e` as `testIdAttribute`, so hooks stay minimal and intentional.

## Test fixtures

`Database\Seeders\BrowserE2eSeeder` prepares deterministic accounts and state:
- `client@test.com / password`;
- `courier@poof.app / password`;
- seeded courier offer/order in `searching + paid` state to exercise accept/start/complete path.

## CI wiring

Workflow job: `.github/workflows/tests.yml` → `browser-e2e`.

The job is **blocking** (no `continue-on-error`) and runs on `pull_request` / `push` events of the Tests workflow.

Artifacts uploaded on every run (especially for failure triage):
- `playwright-report`;
- `test-results` (trace/video/screenshots);
- `storage/logs/e2e-server.log`;
- `storage/logs/laravel.log`.

## Local runbook

```bash
cp .env.example .env
php artisan key:generate
mkdir -p database
touch database/database.sqlite
echo "SESSION_DRIVER=file" >> .env
php artisan migrate:fresh --force
php artisan db:seed --class=BrowserE2eSeeder --force
npm ci
npm run build
npm run e2e:install
php artisan serve --host=127.0.0.1 --port=8000
```

In another terminal:

```bash
npm run e2e:test
```

## Failure triage

1. Open HTML report from `playwright-report/index.html`.
2. Inspect failed test trace/video/screenshot in `test-results`.
3. Correlate with backend evidence in `storage/logs/laravel.log` and `storage/logs/e2e-server.log`.
