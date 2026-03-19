#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/poof}"
API_BASE_URL="${API_BASE_URL:-http://api.poof.com.ua}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-${API_BASE_URL%/}/health}"
PHP_BIN="${PHP_BIN:-php}"
SUPERVISORCTL_BIN="${SUPERVISORCTL_BIN:-supervisorctl}"
REDIS_CLI_BIN="${REDIS_CLI_BIN:-redis-cli}"

run() {
  local description="$1"
  shift

  echo
  echo "==> $description"
  "$@"
}

run "HTTP availability (base URL)" curl --fail --silent --show-error --head "$API_BASE_URL"
run "Health endpoint" curl --fail --silent --show-error "$HEALTHCHECK_URL"
run "Nginx service" systemctl is-active nginx
run "PHP-FPM service" systemctl is-active php8.3-fpm
run "Redis service" systemctl is-active redis-server
run "Cron service" systemctl is-active cron
run "Supervisor workers" "$SUPERVISORCTL_BIN" status
run "Redis ping" "$REDIS_CLI_BIN" ping
run "Laravel scheduler contract" bash -lc "cd '$APP_DIR' && '$PHP_BIN' artisan schedule:list"
run "Worker log tail" bash -lc "cd '$APP_DIR' && tail -n 50 storage/logs/worker.log"
run "Application log tail" bash -lc "cd '$APP_DIR' && tail -n 100 storage/logs/laravel.log"
