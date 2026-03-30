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

1. **F:** runtime bootstrap and bottom-sheet lifecycle on client order-create screen.
2. **A:** client order create flow reaches success confirmation modal.
3. **B:** client profile edit/save round-trip applies updated user name.
4. **D + C:** courier `wire:navigate` and order lifecycle `available -> accept -> start -> complete`.

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
