# Poof API — Production Server Setup & Verification

Проект: **Poof API (Laravel 12)**  
Путь: `/var/www/poof`  
Домен API: `api.poof.com.ua`

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

- `scripts/deploy.sh` — стандартный деплой
- `scripts/rollback.sh <git-ref>` — откат на предыдущий commit/tag
- `scripts/check-server.sh` — быстрый health-check подключений и сервисов

## 9) Минимальный smoke-check после деплоя

```bash
curl -I http://api.poof.com.ua/
cd /var/www/poof && php artisan schedule:list
sudo supervisorctl status
redis-cli ping
tail -n 50 /var/www/poof/storage/logs/worker.log
tail -n 100 /var/www/poof/storage/logs/laravel.log
```
