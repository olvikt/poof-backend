# Incident report: 2026-05-11 / courier_id=2 / subscription dispatch

## Scope
- Target command: `php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2`
- Environment date requested: `2026-05-11`
- Courier: `2`

## Recovery path attempted on 2026-05-11
Documented path used: **C) Composer mirror/proxy allowlist path** (`docs/runtime-bootstrap-recovery.md`).

Commands executed:
1. `bash scripts/check-backend-runtime.sh`
2. `composer install --no-interaction --prefer-dist --no-progress`
3. `bash scripts/check-backend-runtime.sh`

Observed result:
- `runtime_ready=false`
- `missing_vendor=true`
- `artisan_bootstrap=false`
- `composer_network_blocked=true`

`composer install` failed repeatedly with proxy/network errors (`CONNECT tunnel failed, response 403`) when downloading package archives from GitHub-backed dist URLs.

## Diagnose command status
`php artisan poof:diagnose-courier-dispatch --date=2026-05-11 --courier-id=2` is still not executable because bootstrap fails before Laravel starts (`vendor/autoload.php` missing).

Saved output snapshot:
- `docs/incidents/2026-05-11-courier-2-subscription-dispatch-output.json`

## FINAL root cause for this run
- Classification: **другое (operational)**
- Root cause: **runtime bootstrap blocked by dependency delivery path** (proxy/network policy blocks Composer package retrieval; no CI artifact/prebuilt image with `vendor/` provided in this environment).

## Почему courier 2 не видел заказ
На этом запуске это **не может быть подтверждено на реальных данных**, потому что бизнес-диагностика не стартовала: artisan не загрузил приложение.

## Какой exact filter/field это вызвал
Для доменной причины (например, `dispatch_deferred_future_window`, `no_alive_pending_offer`, `no_offer_for_courier`, `not_paid`, queue/timezone) exact field/filter **не определён**, потому что JSON диагностики не был получен.

## Почему это произошло именно 11.05
Потому что на 2026-05-11 в этом контейнере runtime остался без `vendor/`, а сетевой путь для Composer в тот же день возвращает `403` на CONNECT через прокси, что заблокировало bootstrap и финальную dispatch-диагностику.

## Как предотвратить повторение
1. Для incident-окружений хранить и использовать **CI artifact с `vendor/`**, привязанный к commit SHA.
2. Иметь **prebuilt runtime Docker image** с уже установленными зависимостями.
3. Либо поддерживать **официальный composer mirror/allowlist** для `repo.packagist.org`, `api.github.com`, `getcomposer.org`.
4. Добавить preflight gate в CI/job: запуск `bash scripts/check-backend-runtime.sh` до любых incident-команд.

## Recovery action (operational)
- Требуемое действие: поднять runtime через один из уже документированных путей A/B/D (предпочтительно A: CI artifact или B: prebuilt image), затем повторно выполнить diagnose на реальных данных.
- Массовый redispatch **не запускать** до успешного dry-run/diagnose.
