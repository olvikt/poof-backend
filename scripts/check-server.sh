#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/poof}"
API_URL="${API_URL:-http://api.poof.com.ua/}"
PHP_BIN="${PHP_BIN:-php}"

run() {
  echo
  echo "==> $*"
  bash -lc "$*"
}

run "curl -I $API_URL"
run "systemctl is-active nginx"
run "systemctl is-active php8.3-fpm"
run "systemctl is-active redis-server"
run "systemctl is-active cron"
run "supervisorctl status"
run "redis-cli ping"
run "cd $APP_DIR && $PHP_BIN artisan schedule:list"
run "cd $APP_DIR && tail -n 50 storage/logs/worker.log || true"
run "cd $APP_DIR && tail -n 100 storage/logs/laravel.log || true"
