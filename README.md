# Poof Backend (Laravel 12)

## Production operations

Для прод-настройки и проверок сервера см.:

- `docs/production-server-setup.md`

В репозитории есть вспомогательные скрипты для сервера:

- `scripts/deploy.sh` — стандартный deploy (`git pull`, `composer install`, `migrate`, `optimize`, restart workers)
- `scripts/rollback.sh <git-ref>` — откат к commit/tag
- `scripts/check-server.sh` — проверка подключений и ключевых сервисов

> По умолчанию скрипты работают с путём `/var/www/poof`, но это можно переопределить переменной `APP_DIR`.

## Browser E2E (blocking interactive lane)

Локальный прогон минимального browser/e2e lane:

```bash
# важно для e2e-login: persistent session storage
echo "SESSION_DRIVER=file" >> .env
echo "ASSET_URL=http://127.0.0.1:8000" >> .env
php artisan migrate:fresh --force
php artisan db:seed --class=BrowserE2eSeeder --force
npm ci
npm run build
npm run e2e:install
php artisan serve --host=127.0.0.1 --port=8000
# в отдельном терминале
npm run e2e:test
```

Подробности по scope, CI и triage: `docs/browser-e2e-lane.md`.
