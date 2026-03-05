#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/poof}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
SUPERVISORCTL_BIN="${SUPERVISORCTL_BIN:-supervisorctl}"

cd "$APP_DIR"

echo "[deploy] app dir: $APP_DIR"

git pull --ff-only
"$COMPOSER_BIN" install --no-dev --optimize-autoloader
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize
"$SUPERVISORCTL_BIN" restart poof-worker:*

echo "[deploy] done"
