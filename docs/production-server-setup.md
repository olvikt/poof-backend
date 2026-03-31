# Poof API — Production Server Setup & Verification

Проект: **Poof API (Laravel 12)**  
Путь: `/var/www/poof`  
Домен API: `api.poof.com.ua`
Домен приложения: `app.poof.com.ua`
Маркетинговый домен: `poof.com.ua`


Канонический health/smoke target для production: `https://api.poof.com.ua/up`.
Не используйте `/health` и не проверяйте `localhost` на сервере: на этом хосте `localhost` указывает на default nginx site, а не на production API.

## 1) Nginx (virtual host)

### Рекомендуемый конфиг `/etc/nginx/sites-available/poof-api`

```nginx
server {
    listen 80;
    server_name api.poof.com.ua;

    root /var/www/poof/public;
    index index.php index.html;

    access_log /var/log/nginx/poof-api-access.log;
    error_log  /var/log/nginx/poof-api-error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

### Отдельный vhost для `app.poof.com.ua`

Для web/client routes нужен отдельный `server_name app.poof.com.ua`, который указывает на тот же Laravel `public` и проксирует в тот же PHP-FPM пул. DNS/SSL для этого домена настраиваются отдельно и не управляются кодом репозитория.

### Подключение конфига

```bash
sudo ln -sf /etc/nginx/sites-available/poof-api /etc/nginx/sites-enabled/poof-api
sudo nginx -t
sudo systemctl restart nginx
```

## 2) PHP-FPM

```bash
sudo apt install -y php8.3-fpm
sudo systemctl restart php8.3-fpm
sudo systemctl status php8.3-fpm --no-pager
```

## 3) Права Laravel (логи + SQLite)

```bash
cd /var/www/poof

sudo chown -R www-data:www-data storage bootstrap/cache database
sudo chmod -R 775 storage bootstrap/cache database

sudo ls -l /var/www/poof/database/database.sqlite
```

## 4) Scheduler (cron)

Запись в `crontab -e` (root):

```cron
* * * * * cd /var/www/poof && php artisan schedule:run >> /dev/null 2>&1
```

Проверка:

```bash
sudo systemctl status cron --no-pager
cd /var/www/poof && php artisan schedule:list
```

## 5) Redis + Laravel integration

```bash
sudo apt install -y redis-server
sudo systemctl status redis-server --no-pager
redis-cli ping
```

`.env` ключи:

```dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

Проверка в Laravel:

```bash
cd /var/www/poof
php artisan config:cache
php artisan tinker
```

```php
cache()->put('test', 'poof', 60);
cache()->get('test');
app('redis')->ping();
```

## 6) Queue workers (Supervisor)

Файл `/etc/supervisor/conf.d/poof-worker.conf`:

```ini
[program:poof-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/poof/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/poof/storage/logs/worker.log
stopwaitsecs=3600
```

Применение:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start poof-worker:*
sudo supervisorctl status
```

## 7) GitHub flow и safe.directory

Рекомендуемый процесс: только `commit/push` из репозитория, на сервере только `pull`.

Если есть ошибка `dubious ownership`:

```bash
git config --global --add safe.directory /var/www/poof
```

## 8) Деплой и откат (через скрипты репозитория)

См.:

- `scripts/deploy.sh [release-ref]` — canonical deploy path; всегда предпочитайте explicit release tag/ref
- `DEPLOY_REF=<release-ref> bash scripts/deploy.sh` — эквивалентный explicit deploy contract
- `scripts/deploy.sh` без ref — legacy/emergency continuity path only; допустим для backward compatibility, но не как normal flow
- `scripts/rollback.sh <release-ref>` — откат на previous known-good release ref/tag
- `scripts/check-server.sh` — канонический post-deploy smoke-runner; log sections are deploy-relative best-effort context (recent timestamp window with explicit fallback tail label)
- `docs/release-gates.md` — канонический CI/deploy/smoke contract
- `docs/versioned-releases.md` — минимальная versioned release model и operator contract
- `docs/release-candidate-workflow.md` — canonical operator checklist для release candidate deploy/smoke/rollback

Recommended release workflow: см. `docs/release-candidate-workflow.md` для полного end-to-end checklist. Минимальный happy-path для ordinary backend-only release:

```bash
cd /var/www/poof
git fetch --prune --tags origin
bash scripts/deploy.sh release-YYYYMMDD-HHMM
bash scripts/show-release.sh
bash scripts/check-server.sh
```

Что оператор должен проверить в выводе `bash scripts/show-release.sh` после обычного релиза:

- `requested_ref` совпадает с переданным release tag/ref;
- `resolved_ref` совпадает с ожидаемым ref после resolution;
- `selection_mode` = `"explicit"`;
- `previous_release_ref` указывает на предыдущий known-good release;
- `fallback_used` равно `false`;
- `release_history` указывает на `storage/app/release-history.jsonl`.

Legacy/emergency continuity workflow (только если explicit ref временно недоступен или старый вызов нельзя быстро поменять):

```bash
cd /var/www/poof
bash scripts/deploy.sh
bash scripts/show-release.sh
bash scripts/check-server.sh
```

После такого вызова оператор обязан дополнительно убедиться, что:

- warning про fallback path был ожидаемым;
- `fallback_ref` = `origin/main` (или иной настроенный default);
- `selection_mode` = `"fallback"`;
- `fallback_used` = `true`.

Recommended rollback workflow: сначала определить `previous_release_ref` через `bash scripts/show-release.sh`, затем откатываться именно на этот previous known-good release ref. Минимальный flow:

```bash
cd /var/www/poof
bash scripts/show-release.sh
bash scripts/rollback.sh <previous_release_ref>
bash scripts/show-release.sh
bash scripts/check-server.sh
```

`bash scripts/rollback.sh ...` должен не только переключить git ref, но и заново собрать host-side runtime state для выбранного release: `composer install`, `npm ci`, `npm run build`, проверка `public/build/manifest.json`, Laravel cache rebuild, restart воркеров и blocking health-check. Это защищает rollback от ситуации, когда на хосте остаются frontend build artifacts или optimized caches от более нового release.

Если health-check внутри `deploy.sh` или `rollback.sh` не проходит, `storage/app/current-release.json` не обновляется: файл продолжает показывать previous known-good release, а история не получает новую append-only запись. Это нужно, чтобы operator-facing state не указывал на release, который не прошёл blocking health gate.

## 9) Mandatory smoke-check after deploy

Канонический post-deploy contract описан в `docs/release-gates.md`. Базовый запуск:

```bash
cd /var/www/poof && bash scripts/check-server.sh
```

Release не считается завершённым, пока этот smoke-run не прошёл без blocking failures. Скрипт использует канонический health target `https://api.poof.com.ua/up`.

Если релиз затрагивал PWA shell (`public/manifest.json`, `public/sw.js`, landing install UI, Vite asset wiring, cache headers), дополнительно запустите:

```bash
cd /var/www/poof && bash scripts/check-pwa.sh
```

Этот smoke-runner проверяет только стабильную HTTP/rendered-response часть PWA checklist и не заменяет manual browser-level validation install UX/service-worker behavior.

Минимальный operator UX поверх release traceability файлов:

```bash
cd /var/www/poof
bash scripts/show-release.sh
```

Команда читает `storage/app/current-release.json` и `storage/app/release-history.jsonl`, затем печатает:

- current release summary;
- previous known-good release summary;
- последние transition entries из history;
- явную маркировку `EXPLICIT release ref` vs `FALLBACK legacy path`.
