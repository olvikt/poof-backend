#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/poof}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

cd "$APP_DIR"

echo "[deploy] pulling code"
git pull --ff-only

echo "[deploy] installing PHP dependencies"
"$COMPOSER_BIN" install --no-dev --optimize-autoloader

echo "[deploy] installing JS dependencies"
npm ci

echo "[deploy] building frontend assets"
npm run build

echo "[deploy] running migrations"
"$PHP_BIN" artisan migrate --force

echo "[deploy] optimizing Laravel"
"$PHP_BIN" artisan optimize

echo "[deploy] restarting queue workers"
"$PHP_BIN" artisan queue:restart

echo "[deploy] done"
