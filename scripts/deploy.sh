#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/poof}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
SUPERVISORCTL_BIN="${SUPERVISORCTL_BIN:-supervisorctl}"

cd "$APP_DIR"

echo "[deploy] pulling code"

git reset --hard origin/main
git clean -fd
git pull --ff-only

echo "[deploy] installing PHP dependencies"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader

echo "[deploy] running migrations"
"$PHP_BIN" artisan migrate --force

echo "[deploy] optimizing Laravel"
"$PHP_BIN" artisan optimize

echo "[deploy] restarting workers"
"$SUPERVISORCTL_BIN" restart poof-worker:* || true

echo "[deploy] done"
