# Release Gates Contract

Related review contract: [`docs/architecture-rules.md`](./architecture-rules.md).

Этот документ фиксирует канонический release gate для Poof API: что обязательно должно пройти **до деплоя**, **во время деплоя** и **сразу после деплоя**.

## 1. Current gate audit

### Current CI (before this task)

Canonical workflow до изменений: `.github/workflows/tests.yml`.

Что уже было:

- один job `php-tests`;
- PHP 8.3 setup;
- SQLite test environment;
- `php artisan migrate --force`;
- запуск только узкого набора тестов вокруг order flow:
  - `tests/Feature/Api/OrderStoreTest.php`;
  - `tests/Unit/OrderLifecycleStatusContractTest.php`;
  - `tests/Feature/Api --filter=Order`.

Чего не хватало до release gate:

- не было отдельного syntax/lint gate для PHP;
- не было формально описанного current blocking CI gate и отделения его от более широкого test debt;
- не было frontend build verification в CI, хотя deploy зависит от `npm run build` и `public/build/manifest.json`.

### Current deploy (before this task)

Canonical deploy path до изменений: GitHub workflow `.github/workflows/deploy.yml` + server script `scripts/deploy.sh`.

`scripts/deploy.sh` уже выполнял:

- git sync на `main`;
- `composer install --no-dev --optimize-autoloader`;
- `npm ci`;
- `npm run build`;
- проверку `public/build/manifest.json`;
- `php artisan migrate --force`;
- cache clear/optimize;
- restart воркеров через Supervisor;
- health check через `curl -f https://api.poof.com.ua/up || true`.

Versioned-release audit для Task 4.8:

- deploy source был branch-based: `origin/main`;
- release identity была implicit: “текущий commit на main”;
- rollback path уже принимал explicit `<git-ref>`, но operator contract ещё не требовал откатываться именно к previous release ref/tag;
- traceability того, что именно сейчас задеплоено, не была формализована.

Hard-fail шаги уже были:

- install/build/migrate/cache steps из-за `set -euo pipefail`;
- проверка наличия frontend manifest.

Soft-fail шаги до изменений:

- restart workers (`|| true` оставлен как best effort);
- health-check (`|| true`), поэтому deploy считался успешным даже при bad health.

### Current smoke (before this task)

Источники: `scripts/check-server.sh` и `docs/production-server-setup.md`.

Уже были перечислены проверки:

- HTTP availability;
- cron / scheduler;
- Supervisor workers;
- Redis ping;
- tail application / worker logs.

Проблема была в том, что это существовало как полезный checklist, но не как явный обязательный post-deploy contract.

## 2. Blocking gates after this task

### CI gate — must pass before merge / protected push

Canonical workflow: `.github/workflows/tests.yml`.

Blocking jobs:

1. `php-lint`
   - `composer validate --strict`;
   - `php -l` по PHP-файлам приложения, конфигов, routes, scripts и tests.

2. `php-tests`
   - Laravel test environment на SQLite;
   - migrations;
   - coverage reporting for the same blocking suite with CI artifact upload (`php-tests-critical-coverage`), containing:
     - `clover.xml`;
     - HTML report (`html/`);
     - text summary (`summary.txt`).

   Current blocking CI gate:
   - `tests/Feature/Api/OrderStoreTest.php`;
   - `tests/Unit/OrderLifecycleStatusContractTest.php`;
   - `tests/Unit/Support/Address/AddressPrecisionTest.php`;
   - `tests/Unit/Support/Address/AddressCoordinatePolicyTest.php`;
   - `tests/Feature/Admin/AdminProtectedRoutesAuthTest.php`;
   - `tests/Feature/Api/ApiProtectedRoutesAuthTest.php`;
   - `tests/Feature/Auth/RegisterTest.php`;
   - `tests/Feature/Auth/PasswordResetTest.php`;
   - `tests/Feature/Courier/AcceptFlowArchitectureRegressionTest.php`;
   - `tests/Feature/Courier/CourierRuntimeStateSyncTest.php`;
   - `tests/Feature/Courier/AvailableOrdersOnlineSyncTest.php`;
   - `tests/Feature/Courier/CourierOnlineNavigationSyncTest.php`;
   - `tests/Feature/Api/GeocodeControllerTest.php`;
   - `tests/Unit/Address/PrepareAddressSavePayloadTest.php`;
   - `tests/Unit/Address/FilterClientAddressPayloadTest.php`;
   - `tests/Unit/Address/PersistAddressDataTest.php`;
   - `tests/Unit/Address/PersistClientAddressTest.php`;
   - `tests/Unit/Address/ResolveAddressFromPointTest.php`;
   - `tests/Unit/Address/ResolveAddressPointFromFieldsTest.php`;
   - `tests/Unit/Orders/LifecycleActionContractsTest.php`.

   Non-blocking follow-up suites:
   - broader Auth / Courier / Livewire suites (including `CourierOnlineSessionStabilityTest`, `CourierOnlineAutoResyncTest`, `CourierBusyUxFlowTest`, and remaining focused courier online/livewire regressions);
   - remaining wider Unit suites beyond the promoted address precision/policy/reverse-geocode/forward-geocode regression checks, address payload persistence checks, and order lifecycle action contracts.

   Coverage note:
   - coverage is reporting-only in this gate (no minimum threshold enforcement in CI).

3. `frontend-build`
   - `npm ci`;
   - `npm run build`;
   - обязательная проверка `public/build/manifest.json`.

Любой fail в этих jobs — blocking CI failure.

### Deploy gate — must pass during release

Canonical deploy script: `scripts/deploy.sh`.

Blocking during deploy:

- git fetch + explicit ref resolution (`DEPLOY_REF` / positional ref / legacy fallback `origin/main`);
- dependency install;
- frontend build;
- frontend manifest verification;
- DB migrations;
- Laravel cache rebuild;
- release summary check for explicit release tags (`docs/release-summaries/<release-tag>.md`);
- release state recording for the successful/known-good release;
- append-only release history recording;
- blocking health-check against `https://api.poof.com.ua/up`.

Operator note:

- explicit release tag/ref is the canonical release path;
- fallback `origin/main` remains soft-supported only for backward compatibility and emergency continuity;
- no-ref deploys now emit a visible warning and leave `fallback_used=true` / `selection_mode="fallback"` in `storage/app/current-release.json`.
- `storage/app/current-release.json` обновляется только после успешного health-check, а append-only `storage/app/release-history.jsonl` получает новую запись только для successful release transitions.

Health gate теперь считается обязательным: если `https://api.poof.com.ua/up` не отвечает успешно после ограниченного числа retries, deploy завершается с non-zero exit code.

### Post-deploy smoke — mandatory contract

Canonical smoke runners:

- `scripts/check-server.sh` — mandatory release-closing infrastructure/API smoke;
- `scripts/check-pwa.sh` — narrow operator-facing PWA smoke for the HTTP/rendered-response part of the landing/install contract.

Обязательный smoke набор:

1. base HTTP response (`curl -I $API_BASE_URL`);
2. canonical health endpoint (`curl https://api.poof.com.ua/up` via `$HEALTHCHECK_URL`);
3. `php artisan schedule:list`;
4. `supervisorctl status`;
5. `redis-cli ping`;
6. worker log evidence (recent deploy-window context by timestamp; best-effort fallback to tail);
7. application log evidence (recent deploy-window context by timestamp; best-effort fallback to tail).

Дополнительно `scripts/check-server.sh` проверяет systemd state для nginx / php-fpm / redis / cron.

`bash scripts/check-pwa.sh` рекомендуется запускать после PWA-affecting deploys: изменений в `public/sw.js`, `public/manifest.json`, landing page install shell, Vite asset wiring, или production cache config. Он не расширяет blocking release promotion и не заменяет manual/browser-level PWA verification.

## 3. Operations contract

### What must pass in CI before deploy

Должны пройти все jobs из `.github/workflows/tests.yml`:

- PHP syntax/lint;
- current blocking CI gate (with coverage artifact `php-tests-critical-coverage` for the exact same suite);
- frontend build verification.

### What must pass during deploy

`scripts/deploy.sh` должен успешно завершить:

- fetch и resolve выбранного release ref (`DEPLOY_REF` / positional ref / legacy fallback `origin/main`);
- dependency installation;
- frontend build + manifest verification;
- migrations;
- Laravel cache rebuild;
- validation that explicit release tag has a short summary file in `docs/release-summaries/<release-tag>.md`;
- blocking health-check;
- запись release state (`storage/app/current-release.json`) только для successful/known-good release;
- append-only history entry в `storage/app/release-history.jsonl`.

Дополнительный operator contract:

- explicit release ref/tag должен быть передан по умолчанию;
- fallback на `origin/main` допустим только как исключение;
- после no-ref deploy оператор должен явно проверить, что `fallback_used=true` был ожидаемым, а не accidental legacy call.

Failure любого из этих шагов = deploy failed.

### What must pass immediately after deploy

Оператор запускает:

```bash
bash scripts/show-release.sh
bash scripts/check-server.sh
```

И при PWA-affecting изменениях дополнительно запускает:

```bash
bash scripts/check-pwa.sh
```

Обязательный результат:

- все обязательные команды завершаются успешно;
- вывод `bash scripts/show-release.sh` явно подтверждает current release, previous known-good release и deployment mode (`EXPLICIT` vs `FALLBACK`);
- вывод `bash scripts/show-release.sh` также показывает `release_summary_file` и короткий `release_summary` для текущего explicit release;
- recent log-evidence sections не содержат очевидных ошибок, связанных с только что выполненным deploy;
- для PWA-affecting релизов `bash scripts/check-pwa.sh` подтверждает manifest/service-worker/landing HTML wiring без browser automation.

### Who confirms the post-deploy smoke

- Исполнитель деплоя (или on-call engineer) запускает `scripts/check-server.sh` сразу после релиза.
- Release считается закрытым только после успешного smoke-run без blocking failures.

## 4. Versioned release note

Task 4.8 вводит минимальную versioned-release discipline поверх существующего deploy path без большого infra rewrite:

- deploy допускает explicit release ref/tag и продвигает его как canonical normal path;
- fallback на `origin/main` сохраняется как soft-supported legacy/emergency path;
- rollback должен использовать explicit previous release ref/tag;
- production host хранит минимальный traceability state в `storage/app/current-release.json`, включая `requested_ref`, `resolved_ref` и `fallback_used`;
- детальный operator flow описан в [`docs/release-candidate-workflow.md`](./release-candidate-workflow.md), а release model/traceability details — в [`docs/versioned-releases.md`](./versioned-releases.md).
