# Poof Backend (Laravel 12)

## Production operations

Для прод-настройки и проверок сервера см.:

- `docs/production-server-setup.md`

В репозитории есть вспомогательные скрипты для сервера:

- `scripts/deploy.sh` — стандартный deploy (`git pull`, `composer install`, `migrate`, `optimize`, restart workers)
- `scripts/rollback.sh <git-ref>` — откат к commit/tag
- `scripts/check-server.sh` — проверка подключений и ключевых сервисов

> По умолчанию скрипты работают с путём `/var/www/poof`, но это можно переопределить переменной `APP_DIR`.
