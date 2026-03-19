# Release Gates Contract

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
- не было минимального regression gate для Auth / Admin / Courier / Livewire / Unit suites;
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
- health check через `curl -f http://localhost/health || true`.

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
   - критичный regression набор:
     - `tests/Feature/Api`;
     - `tests/Feature/Auth`;
     - `tests/Feature/Admin`;
     - `tests/Feature/Courier`;
     - `tests/Feature/Livewire`;
     - `tests/Unit`.

3. `frontend-build`
   - `npm ci`;
   - `npm run build`;
   - обязательная проверка `public/build/manifest.json`.

Любой fail в этих jobs — blocking CI failure.

### Deploy gate — must pass during release

Canonical deploy script: `scripts/deploy.sh`.

Blocking during deploy:

- dependency install;
- frontend build;
- frontend manifest verification;
- DB migrations;
- Laravel cache rebuild;
- blocking health-check against `/health`.

Health gate теперь считается обязательным: если `/health` не отвечает успешно после ограниченного числа retries, deploy завершается с non-zero exit code.

### Post-deploy smoke — mandatory contract

Canonical smoke runner: `scripts/check-server.sh`.

Обязательный smoke набор:

1. base HTTP response (`curl -I $API_BASE_URL`);
2. health endpoint (`curl $HEALTHCHECK_URL`);
3. `php artisan schedule:list`;
4. `supervisorctl status`;
5. `redis-cli ping`;
6. worker log tail;
7. application log tail.

Дополнительно скрипт проверяет systemd state для nginx / php-fpm / redis / cron.

## 3. Operations contract

### What must pass in CI before deploy

Должны пройти все jobs из `.github/workflows/tests.yml`:

- PHP syntax/lint;
- PHP regression suites;
- frontend build verification.

### What must pass during deploy

`scripts/deploy.sh` должен успешно завершить:

- dependency installation;
- frontend build + manifest verification;
- migrations;
- Laravel cache rebuild;
- blocking health-check.

Failure любого из этих шагов = deploy failed.

### What must pass immediately after deploy

Оператор запускает:

```bash
bash scripts/check-server.sh
```

Обязательный результат:

- все команды завершаются успешно;
- logs не содержат очевидных ошибок, связанных с только что выполненным deploy.

### Who confirms the post-deploy smoke

- Исполнитель деплоя (или on-call engineer) запускает `scripts/check-server.sh` сразу после релиза.
- Release считается закрытым только после успешного smoke-run без blocking failures.

## 4. Notes for later tasks

Task 4.8 (versioned releases) остаётся отдельной задачей, потому что здесь gate формализован поверх текущего branch-based deploy процесса без введения tag/version/promote модели.
